<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        if (! $u) return false;
        // Permission name varies between seeders; accept any reasonable variant.
        return $u->can('appointments.create')
            || $u->can('appointment.create')
            || $u->hasAnyRole(['receptionist', 'hospital_admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            'patient_id'      => ['required', 'integer', 'exists:patients,id'],
            'provider_id'     => ['required', 'integer', 'exists:providers,id'],
            'slot_id'         => ['nullable', 'integer', 'exists:slots,id'],
            'visit_type_id'   => ['nullable', 'integer', 'exists:visit_types,id'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end'   => ['required', 'date', 'after:scheduled_start'],
            'chief_complaint' => ['nullable', 'string', 'max:1000'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'booking_source'  => ['nullable', 'in:walk_in,phone,public_website,chatbot,portal,referral'],
            'fee_amount'      => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
