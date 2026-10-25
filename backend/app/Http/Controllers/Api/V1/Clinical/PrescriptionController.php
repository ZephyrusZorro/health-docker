<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\Clinical\Encounter;
use App\Models\Clinical\Medication;
use App\Models\Clinical\Prescription;
use App\Models\Clinical\PrescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends ApiController
{
    public function index(Request $request)
    {
        $q = Prescription::query()
            ->with(['items.medication', 'patient', 'provider.user'])
            ->when($request->filled('patient_id'),   fn ($q) => $q->where('patient_id', $request->patient_id))
            ->when($request->filled('encounter_id'), fn ($q) => $q->where('encounter_id', $request->encounter_id))
            ->when($request->filled('status'),       fn ($q) => $q->where('status', $request->status))
            ->latest('prescribed_at');

        return PrescriptionResource::collection($q->paginate($request->integer('per_page', 25)));
    }

    public function store(StorePrescriptionRequest $request)
    {
        $data = $request->validated();

        // Resolve patient/provider from encounter when present, otherwise from explicit fields.
        $encounter = null;
        if (! empty($data['encounter_id'])) {
            $encounter = Encounter::findOrFail($data['encounter_id']);
        }

        $patientId  = $data['patient_id']  ?? $encounter?->patient_id;
        $providerId = $data['provider_id'] ?? $encounter?->provider_id ?? auth()->user()?->provider?->id;

        if (! $patientId || ! $providerId) {
            return $this->fail('Either encounter_id or both patient_id and provider_id are required.', 422);
        }

        $prescription = DB::transaction(function () use ($data, $patientId, $providerId, $encounter) {
            $p = Prescription::create([
                'prescription_number' => $this->nextRxNumber(),
                'encounter_id'        => $encounter?->id,
                'patient_id'          => $patientId,
                'provider_id'         => $providerId,
                'status'              => 'active',
                'prescribed_at'       => now(),
                'notes'               => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $med = Medication::find($item['medication_id']);
                PrescriptionItem::create([
                    'prescription_id'  => $p->id,
                    'medication_id'    => $item['medication_id'],
                    'medication_text'  => $med?->displayName() ?? 'Medicine',
                    'strength'         => $med?->strength,
                    'dosage'           => $item['dosage'],
                    'frequency'        => $item['frequency'],
                    'route'            => $item['route'] ?? 'oral',
                    'duration_days'    => $item['duration_days'] ?? null,
                    'quantity'         => $item['quantity'] ?? null,
                    'instructions'     => $item['instructions'] ?? null,
                    'status'           => 'pending',
                ]);
            }

            return $p;
        });

        return $this->created(
            new PrescriptionResource($prescription->load('items.medication')),
            'Prescription issued',
        );
    }

    public function show(Prescription $prescription)
    {
        $prescription->load(['items.medication', 'encounter.patient', 'encounter.provider.user', 'patient', 'provider.user']);
        return $this->ok(new PrescriptionResource($prescription));
    }

    public function destroy(Prescription $prescription)
    {
        $prescription->update(['status' => 'cancelled']);
        return $this->ok(null, 'Prescription cancelled');
    }

    private function nextRxNumber(): string
    {
        $year = now()->year;
        $next = (int) (Prescription::whereYear('created_at', $year)->count() + 1);
        return sprintf('RX-%d-%06d', $year, $next);
    }
}
