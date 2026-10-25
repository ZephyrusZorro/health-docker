<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'appointment_number' => $this->appointment_number,
            'status'             => $this->status,
            'token_number'       => $this->token_number,
            'booking_source'     => $this->booking_source,
            'advance_fee_paid'   => (bool) $this->advance_fee_paid,
            'fee_amount'         => (float) $this->fee_amount,
            'chief_complaint'    => $this->chief_complaint,
            'notes'              => $this->notes,
            'patient_id'         => $this->patient_id,
            'provider_id'        => $this->provider_id,
            'slot_id'            => $this->slot_id,
            'visit_type_id'      => $this->visit_type_id,
            'department_id'      => $this->department_id,
            'scheduled_start'    => $this->scheduled_start?->toIso8601String(),
            'scheduled_end'      => $this->scheduled_end?->toIso8601String(),
            'checked_in_at'      => $this->checked_in_at?->toIso8601String(),
            'consult_started_at' => $this->consult_started_at?->toIso8601String(),
            'consult_ended_at'   => $this->consult_ended_at?->toIso8601String(),
            'cancelled_at'       => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason'=> $this->cancellation_reason,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id'         => $this->patient->id,
                'uhid'       => $this->patient->uhid,
                'first_name' => $this->patient->first_name,
                'last_name'  => $this->patient->last_name,
                'full_name'  => trim("{$this->patient->first_name} {$this->patient->last_name}"),
                'phone'      => $this->patient->phone,
                'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
                'age'        => $this->patient->date_of_birth ? (int) $this->patient->date_of_birth->diffInYears(now()) : null,
                'gender'     => $this->patient->gender,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => [
                'id'             => $this->provider->id,
                'user'           => $this->provider->user ? [
                    'id'   => $this->provider->user->id,
                    'name' => $this->provider->user->name,
                ] : null,
                'specialization' => $this->provider->specialization,
                'department_id'  => $this->provider->department_id,
            ]),
            'visit_type' => $this->whenLoaded('visitType', fn () => $this->visitType ? [
                'id'    => $this->visitType->id,
                'name'  => $this->visitType->name,
                'color' => $this->visitType->color,
            ] : null),
        ];
    }
}
