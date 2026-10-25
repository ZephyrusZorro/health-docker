<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'patient_id'     => $this->id, // frontend convenience
            'uhid'           => $this->uhid,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'full_name'      => trim("{$this->first_name} {$this->last_name}"),
            'preferred_name' => $this->preferred_name,
            'gender'         => $this->gender,
            'date_of_birth'  => $this->date_of_birth?->toDateString(),
            'age'            => $this->date_of_birth ? (int) $this->date_of_birth->diffInYears(now()) : null,
            'blood_group'    => $this->blood_group,
            'marital_status' => $this->marital_status,
            'occupation'     => $this->occupation,
            'phone'          => $this->phone,
            'phone_alt'      => $this->phone_alt,
            'email'          => $this->email,
            'address'        => $this->address,
            'emergency_contact' => $this->emergency_contact,
            'payer_type'     => $this->payer_type,
            'insurance_provider' => $this->insurance_provider,
            'insurance_number'   => $this->insurance_number,
            'referred_by'    => $this->referred_by,
            'is_vip'         => (bool) $this->is_vip,
            'is_deceased'    => (bool) $this->is_deceased,
            'registered_at'  => $this->registered_at?->toIso8601String(),
            'facility_id'    => $this->facility_id,
            'notes'          => $this->when(
                $request->user()?->hasAnyRole(['doctor', 'nurse', 'super_admin', 'hospital_admin']) ?? false,
                $this->notes,
            ),
            'documents_count'   => $this->whenCounted('documents'),
            'encounters_count'  => $this->whenCounted('encounters'),
            'appointments_count'=> $this->whenCounted('appointments'),
        ];
    }
}
