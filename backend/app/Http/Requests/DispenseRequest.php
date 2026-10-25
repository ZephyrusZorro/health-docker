<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        if (! $u) return false;
        return $u->can('pharmacy.dispense')
            || $u->hasAnyRole(['pharmacist', 'hospital_admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            'patient_id'      => ['required', 'integer', 'exists:patients,id'],
            'prescription_id' => ['nullable', 'integer', 'exists:prescriptions,id'],
            'encounter_id'    => ['nullable', 'integer', 'exists:encounters,id'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.medication_id' => ['required', 'integer', 'exists:medications,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.prescription_item_id' => ['nullable', 'integer', 'exists:prescription_items,id'],
            'items.*.instructions' => ['nullable', 'string'],
        ];
    }
}
