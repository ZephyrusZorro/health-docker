<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('emr.consult')
            || $u->can('encounter.create')
            || $u->hasAnyRole(['doctor', 'nurse', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'patient_id'                 => ['required', 'integer', 'exists:patients,id'],
            'provider_id'                => ['nullable', 'integer', 'exists:providers,id'],
            'appointment_id'             => ['nullable', 'integer', 'exists:appointments,id'],
            'department_id'              => ['nullable', 'integer', 'exists:departments,id'],
            'encounter_type'             => ['nullable', 'in:opd,ipd,emergency,telemedicine,home_visit'],
            'chief_complaint'            => ['nullable', 'string', 'max:2000'],
            'history_of_present_illness' => ['nullable', 'string', 'max:5000'],
            'examination_findings'       => ['nullable', 'string', 'max:5000'],
            'assessment'                 => ['nullable', 'string', 'max:5000'],
            'plan'                       => ['nullable', 'string', 'max:5000'],
            'advice_to_patient'          => ['nullable', 'string', 'max:2000'],
            'follow_up_date'             => ['nullable', 'date'],
        ];
    }

    /** Accept legacy shape `type` → `encounter_type`. */
    public function prepareForValidation(): void
    {
        if ($this->filled('type') && ! $this->filled('encounter_type')) {
            $this->merge(['encounter_type' => $this->input('type')]);
        }
    }
}
