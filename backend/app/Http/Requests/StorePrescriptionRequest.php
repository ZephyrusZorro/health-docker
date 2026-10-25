<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('rx.issue')
            || $u->can('prescription.create')
            || $u->hasAnyRole(['doctor', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'patient_id'             => ['nullable', 'integer', 'exists:patients,id'],
            'provider_id'            => ['nullable', 'integer', 'exists:providers,id'],
            'encounter_id'           => ['nullable', 'integer', 'exists:encounters,id'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.medication_id'  => ['required', 'integer', 'exists:medications,id'],
            'items.*.dosage'         => ['required', 'string', 'max:50'],
            'items.*.frequency'      => ['required', 'string', 'max:50'],
            'items.*.route'          => ['nullable', 'string', 'max:30'],
            'items.*.duration_days'  => ['required', 'integer', 'min:1', 'max:365'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
            'items.*.instructions'   => ['nullable', 'string', 'max:500'],
        ];
    }
}
