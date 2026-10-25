<?php

namespace App\Http\Controllers\Api\V1\Admission;

use App\Events\BedAssigned;
use App\Events\PatientDischarged;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\AdmitPatientRequest;
use App\Models\Admission\Admission;
use App\Models\Admission\Bed;
use App\Models\Admission\BedAssignment;
use App\Models\Admission\BedStatusHistory;
use App\Models\Admission\DischargeSummary;
use App\Services\Billing\InvoiceAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * IPD admission lifecycle (SRS §4.4 + §8.3):
 *   admit → bed assign → daily notes → discharge summary → final bill → close.
 *
 * All transitions are transactional and emit events for audit + realtime
 * dashboards.
 */
class AdmissionController extends ApiController
{
    public function __construct(private readonly InvoiceAggregator $billing)
    {
    }

    public function index(Request $request)
    {
        $q = Admission::query()
            ->with([
                'patient',
                'admittingDoctor.user',
                'currentBed.room.ward',
                'department',
            ])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('status'),     fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->patient_id))
            ->when($request->boolean('active_only'), fn ($q) => $q->whereIn('status', ['pending', 'active']))
            ->latest('admitted_at');

        return $q->paginate($request->integer('per_page', 20));
    }

    public function store(AdmitPatientRequest $request)
    {
        $data = $request->validated();

        $admission = DB::transaction(function () use ($data) {
            $bed = Bed::lockForUpdate()->findOrFail($data['bed_id']);
            if ($bed->status !== Bed::STATUS_AVAILABLE) {
                abort(409, "Bed {$bed->bed_number} is not available (status: {$bed->status}).");
            }

            $admission = Admission::create([
                'admission_number'     => $this->nextAdmissionNumber(),
                'facility_id'          => app('facility')?->id ?? $bed->facility_id,
                'patient_id'           => $data['patient_id'],
                'admitting_doctor_id'  => $data['admitting_doctor_id'],
                'department_id'        => $data['department_id'] ?? null,
                'current_bed_id'       => $bed->id,
                'admission_type'       => $data['admission_type'],
                'admission_reason'     => $data['admission_reason'],
                'provisional_diagnosis'=> $data['provisional_diagnosis'] ?? null,
                'status'               => Admission::STATUS_ACTIVE,
                'admitted_at'          => now(),
                'admitted_by'          => auth()->id() ?? \App\Models\Identity\User::value('id'),
            ]);

            $assignment = BedAssignment::create([
                'admission_id'        => $admission->id,
                'bed_id'              => $bed->id,
                'patient_id'          => $admission->patient_id,
                'assigned_by'         => auth()->id() ?? \App\Models\Identity\User::value('id'),
                'assigned_at'         => now(),
                'daily_rate_snapshot' => $bed->currentRate(),
            ]);

            $previous = $bed->status;
            $bed->update(['status' => Bed::STATUS_OCCUPIED]);

            BedStatusHistory::create([
                'bed_id'          => $bed->id,
                'patient_id'      => $admission->patient_id,
                'admission_id'    => $admission->id,
                'changed_by'      => auth()->id(),
                'status'          => Bed::STATUS_OCCUPIED,
                'previous_status' => $previous,
                'start_at'        => now(),
            ]);

            event(new BedAssigned($assignment));
            return $admission;
        });

        return $this->created(
            $admission->load(['patient', 'currentBed.room.ward']),
            'Patient admitted',
        );
    }

    public function show(Admission $admission)
    {
        $admission->load([
            'patient',
            'admittingDoctor.user',
            'department',
            'bedAssignments.bed.room.ward',
            'currentBed.room.ward',
            'dischargeSummary',
        ]);
        return $this->ok($admission);
    }

    /** Transfer patient to a different bed. Closes current assignment, opens new. */
    public function transferBed(Request $request, Admission $admission)
    {
        $data = $request->validate([
            'new_bed_id' => ['required', 'integer', 'exists:beds,id'],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($admission, $data) {
            $newBed = Bed::lockForUpdate()->findOrFail($data['new_bed_id']);
            if ($newBed->status !== Bed::STATUS_AVAILABLE) {
                abort(409, 'Target bed is not available.');
            }

            // Vacate current assignment
            $current = $admission->bedAssignments()->whereNull('vacated_at')->latest('assigned_at')->first();
            if ($current) {
                $current->update(['vacated_at' => now()]);
                if ($oldBed = $current->bed) {
                    $oldBed->update(['status' => Bed::STATUS_CLEANING]);
                    BedStatusHistory::create([
                        'bed_id'          => $oldBed->id,
                        'patient_id'      => $admission->patient_id,
                        'admission_id'    => $admission->id,
                        'changed_by'      => auth()->id(),
                        'status'          => Bed::STATUS_CLEANING,
                        'previous_status' => Bed::STATUS_OCCUPIED,
                        'start_at'        => now(),
                    ]);
                }
            }

            // Open new assignment
            BedAssignment::create([
                'admission_id'        => $admission->id,
                'bed_id'              => $newBed->id,
                'patient_id'          => $admission->patient_id,
                'assigned_by'         => auth()->id(),
                'assigned_at'         => now(),
                'transfer_reason'     => $data['reason'] ?? null,
                'daily_rate_snapshot' => $newBed->currentRate(),
            ]);
            $newBed->update(['status' => Bed::STATUS_OCCUPIED]);
            $admission->update(['current_bed_id' => $newBed->id]);

            BedStatusHistory::create([
                'bed_id'          => $newBed->id,
                'patient_id'      => $admission->patient_id,
                'admission_id'    => $admission->id,
                'changed_by'      => auth()->id(),
                'status'          => Bed::STATUS_OCCUPIED,
                'previous_status' => Bed::STATUS_AVAILABLE,
                'start_at'        => now(),
            ]);
        });

        return $this->ok(null, 'Transferred');
    }

    /**
     * Discharge flow. SRS §4.4: final bill must be cleared before regular
     * discharge; LAMA / abscond / death paths bypass the bill check.
     */
    public function discharge(Request $request, Admission $admission)
    {
        $data = $request->validate([
            'discharge_type'         => ['required', 'in:routine,against_medical_advice,referral,absconded,death,transfer'],
            'discharge_diagnosis'    => ['required', 'string', 'max:2000'],
            'condition_at_discharge' => ['nullable', 'string', 'max:1000'],
            'course_in_hospital'     => ['nullable', 'string', 'max:2000'],
            'treatment_given'        => ['nullable', 'string', 'max:2000'],
            'discharge_medications'  => ['nullable', 'string', 'max:2000'],
            'follow_up_advice'       => ['nullable', 'string', 'max:1000'],
            'follow_up_date'         => ['nullable', 'date', 'after:today'],
            'diet_advice'            => ['nullable', 'string', 'max:1000'],
            'activity_restrictions'  => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($admission, $data) {
            // 1. Final bill aggregation (bed days, dispenses, lab, encounter charges).
            $invoice = $this->billing->createOrUpdateForAdmission($admission);

            // 2. Block routine discharge if balance outstanding (SRS §4.4 rule).
            if ($data['discharge_type'] === 'routine'
                && $invoice
                && (float) $invoice->amount_due > 0) {
                abort(422, "Cannot discharge: outstanding balance of {$invoice->amount_due}. Settle the bill before discharge.");
            }

            // 3. Vacate current bed → cleaning state.
            $current = $admission->bedAssignments()->whereNull('vacated_at')->latest('assigned_at')->first();
            if ($current) {
                $current->update(['vacated_at' => now()]);
                if ($bed = $current->bed) {
                    $bed->update(['status' => Bed::STATUS_CLEANING]);
                    BedStatusHistory::create([
                        'bed_id'          => $bed->id,
                        'patient_id'      => $admission->patient_id,
                        'admission_id'    => $admission->id,
                        'changed_by'      => auth()->id(),
                        'status'          => Bed::STATUS_CLEANING,
                        'previous_status' => Bed::STATUS_OCCUPIED,
                        'start_at'        => now(),
                    ]);
                }
            }

            // 4. Build discharge summary.
            DischargeSummary::create([
                'admission_id'           => $admission->id,
                'prepared_by'            => auth()->id() ?? \App\Models\Identity\User::value('id'),
                'discharge_diagnosis'    => $data['discharge_diagnosis'],
                'condition_at_discharge' => $data['condition_at_discharge'] ?? null,
                'course_in_hospital'     => $data['course_in_hospital'] ?? null,
                'treatment_given'        => $data['treatment_given'] ?? null,
                'discharge_medications'  => $data['discharge_medications'] ?? null,
                'follow_up_advice'       => $data['follow_up_advice'] ?? null,
                'follow_up_date'         => $data['follow_up_date'] ?? null,
                'diet_advice'            => $data['diet_advice'] ?? null,
                'activity_restrictions'  => $data['activity_restrictions'] ?? null,
                'is_finalized'           => true,
                'finalized_at'           => now(),
            ]);

            // 5. Close admission.
            $admission->update([
                'status'         => Admission::STATUS_DISCHARGED,
                'discharged_at'  => now(),
                'discharged_by'  => auth()->id(),
                'discharge_type' => $data['discharge_type'],
                'current_bed_id' => null,
            ]);

            event(new PatientDischarged($admission));
        });

        return $this->ok(null, 'Patient discharged');
    }

    private function nextAdmissionNumber(): string
    {
        $year = now()->year;
        $next = (int) (Admission::whereYear('created_at', $year)->count() + 1);
        return sprintf('ADM-%d-%06d', $year, $next);
    }
}
