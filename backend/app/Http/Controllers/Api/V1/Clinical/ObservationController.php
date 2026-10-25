<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StoreVitalsRequest;
use App\Http\Resources\ObservationResource;
use App\Models\Clinical\Encounter;
use App\Models\Clinical\Observation;
use App\Services\Clinical\VitalsRecorder;
use Illuminate\Http\Request;

/**
 * Observations = vitals + any FHIR Observation. Vitals are typed via
 * VitalsRecorder so the catalogue of LOINC codes lives in one place.
 *
 * SRS §4.3 OPD Workflow, §4.6 EMR Patient Chart.
 */
class ObservationController extends ApiController
{
    public function __construct(private readonly VitalsRecorder $vitals) {}

    /** Record a batch of vitals against an encounter (nurse station). */
    public function storeVitals(StoreVitalsRequest $request, Encounter $encounter)
    {
        $rows = $this->vitals->record($encounter, $request->validated());
        return $this->created(ObservationResource::collection($rows), 'Vitals recorded');
    }

    /** All observations for an encounter. */
    public function indexForEncounter(Encounter $encounter)
    {
        return ObservationResource::collection(
            $encounter->observations()
                ->with('recordedBy:id,name')
                ->orderBy('effective_at')
                ->get()
        );
    }

    /**
     * Patient-wide observation history for chart graphing.
     * Accepts ?code=8480-6 (single metric) and ?category=vital-signs.
     */
    public function indexForPatient(Request $request, int $patientId)
    {
        $q = Observation::where('patient_id', $patientId)
            ->when($request->filled('code'),     fn ($q) => $q->where('code', $request->code))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->orderBy('effective_at');

        return ObservationResource::collection($q->get());
    }
}
