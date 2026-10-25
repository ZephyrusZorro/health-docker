<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Events\EncounterClosed;
use App\Events\EncounterStarted;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StoreEncounterRequest;
use App\Http\Requests\StoreVitalsRequest;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\ObservationResource;
use App\Models\Clinical\Encounter;
use App\Models\Clinical\Observation;
use App\Services\Billing\InvoiceAggregator;
use App\Services\Clinical\VitalsRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Encounter (FHIR Encounter) controller — open, view, update, close OPD/IPD
 * encounters and capture vitals. Closing an encounter triggers billing
 * aggregation per SRS §4.9.
 */
class EncounterController extends ApiController
{
    public function __construct(
        private readonly InvoiceAggregator $billing,
        private readonly VitalsRecorder $vitals,
    ) {
    }

    public function index(Request $request)
    {
        $q = Encounter::query()
            ->with(['patient', 'provider.user'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('patient_id'),  fn ($q) => $q->where('patient_id',  $request->patient_id))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->provider_id))
            ->when($request->filled('status'),      fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'),        fn ($q) => $q->where('encounter_type', $request->type))
            ->latest('started_at');

        return EncounterResource::collection($q->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreEncounterRequest $request)
    {
        $data     = $request->validated();
        $facility = app('facility');

        $encounter = Encounter::create(array_merge($data, [
            'facility_id'      => $facility?->id ?? \App\Models\Identity\Facility::value('id'),
            'encounter_number' => $this->nextEncounterNumber(),
            'status'           => 'in_progress',
            'started_at'       => now(),
            'created_by'       => auth()->id(),
        ]));

        event(new EncounterStarted($encounter));

        return $this->created(
            new EncounterResource($encounter->load(['patient', 'provider.user'])),
            'Encounter started',
        );
    }

    public function show(Encounter $encounter)
    {
        $encounter->load([
            'patient', 'provider.user',
            'observations' => fn ($q) => $q->orderBy('effective_at'),
            'conditions',
            'prescriptions.items.medication',
            'orders.items',
        ]);
        return $this->ok(new EncounterResource($encounter));
    }

    public function update(Request $request, Encounter $encounter)
    {
        $data = $request->validate([
            'chief_complaint'             => ['sometimes', 'nullable', 'string'],
            'history_of_present_illness'  => ['sometimes', 'nullable', 'string'],
            'examination_findings'        => ['sometimes', 'nullable', 'string'],
            'assessment'                  => ['sometimes', 'nullable', 'string'],
            'plan'                        => ['sometimes', 'nullable', 'string'],
            'advice_to_patient'           => ['sometimes', 'nullable', 'string'],
            'clinical_summary'            => ['sometimes', 'nullable', 'string'],
            'follow_up_date'              => ['sometimes', 'nullable', 'date'],
        ]);
        $encounter->update($data);
        return $this->ok(new EncounterResource($encounter->fresh()), 'Encounter updated');
    }

    public function close(Encounter $encounter)
    {
        if ($encounter->status === 'completed') {
            return $this->fail('Encounter already closed.', 422);
        }

        DB::transaction(function () use ($encounter) {
            $encounter->update([
                'status'   => 'completed',
                'ended_at' => now(),
            ]);

            // Generate / refresh invoice — SRS §4.9 source-linked billing.
            try {
                $this->billing->createOrUpdateForEncounter($encounter);
            } catch (\Throwable $e) {
                // Billing failures shouldn't block clinical closure; log & continue.
                logger()->error('Invoice aggregation failed', ['encounter' => $encounter->id, 'err' => $e->getMessage()]);
            }
        });

        event(new EncounterClosed($encounter));

        return $this->ok(new EncounterResource($encounter->fresh()), 'Encounter completed');
    }

    /**
     * Record vitals — the flat clinical-form payload becomes a set of FHIR
     * Observation rows tagged with category=vital-signs and well-known codes.
     */
    public function recordVitals(StoreVitalsRequest $request, Encounter $encounter)
    {
        $observations = $this->vitals->record($encounter, $request->validated());
        return $this->ok(
            ObservationResource::collection($observations),
            count($observations) . ' vital observation(s) recorded',
        );
    }

    public function observations(Encounter $encounter)
    {
        return $this->ok(
            ObservationResource::collection(
                $encounter->observations()->orderBy('effective_at')->get()
            )
        );
    }

    private function nextEncounterNumber(): string
    {
        $year = now()->year;
        $next = (int) (Encounter::whereYear('created_at', $year)->count() + 1);
        return sprintf('ENC-%d-%06d', $year, $next);
    }
}
