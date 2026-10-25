<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'encounter_id'   => $this->encounter_id,
            'patient_id'     => $this->patient_id,
            'code'           => $this->code,
            'code_system'    => $this->code_system,
            'display_name'   => $this->display_name,
            'category'       => $this->category,
            'value_type'     => $this->value_type,
            'value_numeric'  => $this->value_numeric !== null ? (float) $this->value_numeric : null,
            'value_string'   => $this->value_string,
            'value_boolean'  => $this->value_boolean,
            'unit'           => $this->unit,
            'reference_low'  => $this->reference_low !== null ? (float) $this->reference_low : null,
            'reference_high' => $this->reference_high !== null ? (float) $this->reference_high : null,
            'interpretation' => $this->interpretation,
            'is_abnormal'    => in_array($this->interpretation, ['low', 'high', 'critical_low', 'critical_high', 'abnormal'], true),
            'effective_at'   => $this->effective_at?->toIso8601String(),
            'recorded_by'    => $this->recorded_by,
            'notes'          => $this->notes,
        ];
    }
}
