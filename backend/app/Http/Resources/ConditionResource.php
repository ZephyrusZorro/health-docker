<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'patient_id'          => $this->patient_id,
            'encounter_id'        => $this->encounter_id,
            'code'                => $this->code,
            'code_system'         => $this->code_system,
            'display_name'        => $this->display_name,
            'clinical_status'     => $this->clinical_status,
            'verification_status' => $this->verification_status,
            'severity'            => $this->severity,
            'category'            => $this->category,
            'onset_date'          => $this->onset_date?->toDateString(),
            'abatement_date'      => $this->abatement_date?->toDateString(),
            'notes'               => $this->notes,
            'recorded_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
