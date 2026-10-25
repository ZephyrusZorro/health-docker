<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('lab.order')
            || $u->can('lab_order.create')
            || $u->hasAnyRole(['doctor', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'patient_id'      => ['required', 'integer', 'exists:patients,id'],
            'encounter_id'    => ['nullable', 'integer', 'exists:encounters,id'],
            'priority'        => ['nullable', 'in:routine,urgent,stat'],
            'clinical_note'   => ['nullable', 'string', 'max:1000'],
            'clinical_notes'  => ['nullable', 'string', 'max:1000'],
            'test_ids'        => ['required', 'array', 'min:1'],
            'test_ids.*'      => ['integer', 'exists:lab_tests,id'],
        ];
    }
}
