<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdmitPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('ipd.admit')
            || $u->can('admission.create')
            || $u->hasAnyRole(['receptionist', 'doctor', 'nurse', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'patient_id'             => ['required', 'integer', 'exists:patients,id'],
            'admitting_doctor_id'    => ['required', 'integer', 'exists:providers,id'],
            'department_id'          => ['nullable', 'integer', 'exists:departments,id'],
            'bed_id'                 => ['required', 'integer', 'exists:beds,id'],
            'admission_reason'       => ['required', 'string', 'max:2000'],
            'provisional_diagnosis'  => ['nullable', 'string', 'max:1000'],
            'admission_type'         => ['required', 'in:planned,emergency,transfer,day_care,observation'],
        ];
    }

    /**
     * Backwards-compat alias: we previously called this `admitting_provider_id`.
     * Accept either form transparently.
     */
    public function prepareForValidation(): void
    {
        if ($this->filled('admitting_provider_id') && ! $this->filled('admitting_doctor_id')) {
            $this->merge(['admitting_doctor_id' => $this->input('admitting_provider_id')]);
        }
    }
}
