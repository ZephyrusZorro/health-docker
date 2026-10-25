<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('patients.create')
            || $u->can('patient.create')
            || $u->hasAnyRole(['receptionist', 'doctor', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'gender'         => ['required', 'in:male,female,other,unknown'],
            'date_of_birth'  => ['required', 'date', 'before_or_equal:today'],
            'is_dob_estimated' => ['sometimes', 'boolean'],
            'blood_group'    => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'occupation'     => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'phone_alt'      => ['nullable', 'string', 'max:20'],
            'email'          => ['nullable', 'email', 'max:150'],
            'address'        => ['nullable', 'array'],
            'address.line1'  => ['nullable', 'string'],
            'address.city'   => ['nullable', 'string'],
            'address.state'  => ['nullable', 'string'],
            'address.pincode' => ['nullable', 'string'],
            'address.country' => ['nullable', 'string'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string'],
            'emergency_contact.phone'=> ['nullable', 'string'],
            'emergency_contact.relationship' => ['nullable', 'string'],
            'payer_type'     => ['nullable', 'in:self,insurance,corporate,government,charity'],
            'insurance_provider' => ['nullable', 'string', 'max:100'],
            'insurance_number'   => ['nullable', 'string', 'max:100'],
            'referred_by'    => ['nullable', 'string', 'max:150'],
            'national_id'    => ['nullable', 'array'],
            'notes'          => ['nullable', 'string'],
            'is_vip'         => ['sometimes', 'boolean'],
        ];
    }
}
