<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('patients.update')
            || $u->can('patient.edit')
            || $u->hasAnyRole(['receptionist', 'doctor', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        // UHID is immutable (SRS §4.1) — not editable here.
        return [
            'first_name'     => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender'         => ['sometimes', 'in:male,female,other,unknown'],
            'date_of_birth'  => ['sometimes', 'date', 'before_or_equal:today'],
            'blood_group'    => ['sometimes', 'nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'phone'          => ['sometimes', 'nullable', 'string', 'max:20'],
            'email'          => ['sometimes', 'nullable', 'email', 'max:150'],
            'address'        => ['sometimes', 'nullable', 'array'],
            'emergency_contact' => ['sometimes', 'nullable', 'array'],
            'payer_type'     => ['sometimes', 'in:self,insurance,corporate,government,charity'],
            'notes'          => ['sometimes', 'nullable', 'string'],
            'is_vip'         => ['sometimes', 'boolean'],
        ];
    }
}
