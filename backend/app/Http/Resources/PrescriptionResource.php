<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'prescription_number' => $this->prescription_number,
            'patient_id'          => $this->patient_id,
            'provider_id'         => $this->provider_id,
            'encounter_id'        => $this->encounter_id,
            'status'              => $this->status,
            'prescribed_at'       => $this->prescribed_at?->toIso8601String(),
            'issued_at'           => $this->prescribed_at?->toIso8601String(), // alias
            'valid_until'         => $this->valid_until?->toDateString(),
            'notes'               => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id'         => $this->patient->id,
                'uhid'       => $this->patient->uhid,
                'first_name' => $this->patient->first_name,
                'last_name'  => $this->patient->last_name,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => [
                'id'   => $this->provider->id,
                'user' => $this->provider->user ? [
                    'id'   => $this->provider->user->id,
                    'name' => $this->provider->user->name,
                ] : null,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'              => $i->id,
                'prescription_id' => $i->prescription_id,
                'medication_id'   => $i->medication_id,
                'medication_text' => $i->medication_text,
                'medication'      => $i->medication ? [
                    'id'           => $i->medication->id,
                    'generic_name' => $i->medication->generic_name,
                    'brand_name'   => $i->medication->brand_name,
                    'display_name' => $i->medication->displayName(),
                    'strength'     => $i->medication->strength,
                    'form'         => $i->medication->form,
                ] : null,
                'strength'        => $i->strength,
                'dosage'          => $i->dosage,
                'frequency'       => $i->frequency,
                'duration_days'   => $i->duration_days,
                'quantity'        => $i->quantity,
                'route'           => $i->route,
                'instructions'    => $i->instructions,
                'status'          => $i->status,
                'dispensed_quantity' => $i->dispensed_quantity,
            ])),
        ];
    }
}
