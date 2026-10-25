<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Events\PatientRegistered;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Services\Identity\UhidGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Patient CRUD + search + merge. All clinical data traces back here.
 */
class PatientController extends ApiController
{
    public function __construct(private readonly UhidGenerator $uhids)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $search = $request->input('q', $request->input('search'));

        $q = Patient::query()
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($search, fn ($q) => $q->search($search))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->when($request->filled('gender'),     fn ($q) => $q->where('gender', $request->gender))
            ->when($request->filled('payer_type'), fn ($q) => $q->where('payer_type', $request->payer_type))
            ->orderByDesc('registered_at');

        return PatientResource::collection(
            $q->paginate($request->integer('per_page', 25))
        );
    }

    public function store(StorePatientRequest $request)
    {
        $facility = app('facility') ?? Facility::firstOrFail();

        $patient = DB::transaction(function () use ($request, $facility) {
            $data = $request->validated();
            $data['facility_id']   = $facility->id;
            $data['uhid']          = $this->uhids->generate($facility);
            $data['registered_at'] = now();

            return Patient::create($data);
        });

        event(new PatientRegistered($patient));

        return $this->created(new PatientResource($patient), 'Patient registered');
    }

    public function show(Patient $patient)
    {
        $this->authorize('view', $patient);
        $patient->loadCount(['documents', 'encounters', 'appointments'])
                ->load('contacts');
        return $this->ok(new PatientResource($patient));
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $patient->update($request->validated());
        return $this->ok(new PatientResource($patient->fresh()), 'Patient updated');
    }

    public function destroy(Patient $patient)
    {
        $this->authorize('delete', $patient);
        $patient->delete(); // soft delete
        return $this->ok(null, 'Patient deactivated');
    }

    /**
     * Duplicate detection — same phone or same name+DOB combos flagged.
     * SRS §4.1: intelligent duplicate detection.
     */
    public function duplicates(Patient $patient)
    {
        $matches = Patient::query()
            ->where('id', '!=', $patient->id)
            ->where('facility_id', $patient->facility_id)
            ->where(function ($q) use ($patient) {
                if ($patient->phone) {
                    $q->where('phone', $patient->phone);
                }
                $q->orWhere(function ($q) use ($patient) {
                    $q->where('first_name', 'ILIKE', $patient->first_name)
                      ->where('date_of_birth', $patient->date_of_birth);
                });
            })
            ->limit(20)
            ->get();

        return $this->ok(PatientResource::collection($matches));
    }

    /**
     * Merge patient B into patient A — SRS §4.1 merge workflow.
     * Both stay in the DB (soft link) so historical records remain auditable.
     */
    public function merge(Request $request, Patient $patient)
    {
        $this->authorize('merge', $patient);
        $data = $request->validate([
            'merge_into_id' => ['required', 'integer', 'exists:patients,id', 'different:' . $patient->id],
        ]);

        DB::transaction(function () use ($patient, $data) {
            $target = Patient::findOrFail($data['merge_into_id']);

            // Move clinical records to the surviving identity.
            $patient->appointments()->update(['patient_id' => $target->id]);
            $patient->encounters()->update(['patient_id' => $target->id]);
            $patient->admissions()->update(['patient_id' => $target->id]);
            $patient->invoices()->update(['patient_id' => $target->id]);
            $patient->documents()->update(['patient_id' => $target->id]);

            $patient->update(['merged_into_patient_id' => $target->id]);
            $patient->delete();
        });

        return $this->ok(null, 'Patient merged');
    }
}
