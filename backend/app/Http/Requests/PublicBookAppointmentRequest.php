<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicBookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — rate limited at the route level.
    }

    public function rules(): array
    {
        return [
            // Patient lookup — either an existing UHID+phone combo, or a new registration.
            'patient_uhid'    => ['nullable', 'string', 'exists:patients,uhid'],
            'phone'           => ['required', 'string', 'max:20'],

            // If a new patient, these are required:
            'first_name'      => ['required_without:patient_uhid', 'string', 'max:100'],
            'last_name'       => ['nullable', 'string', 'max:100'],
            'gender'          => ['required_without:patient_uhid', 'in:male,female,other,unknown'],
            'date_of_birth'   => ['required_without:patient_uhid', 'date', 'before_or_equal:today'],
            'email'           => ['nullable', 'email'],

            // Appointment details:
            'provider_id'     => ['required', 'integer', 'exists:providers,id'],
            'slot_id'         => ['required', 'integer', 'exists:slots,id'],
            'chief_complaint' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
