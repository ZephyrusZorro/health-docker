<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'generic_name' => $this->generic_name,
            'brand_name'   => $this->brand_name,
            'display_name' => $this->displayName(),
            'strength'     => $this->strength,
            'form'         => $this->form,
            'category'     => $this->category,
            'manufacturer' => $this->manufacturer,
            'is_prescription_required' => $this->is_prescription_required,
            'is_controlled'            => $this->is_controlled,
            'is_active'                => $this->is_active,
            'total_stock'  => $this->when(isset($this->total_stock), $this->total_stock),
        ];
    }
}
