<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'encounter_number'            => $this->encounter_number,
            'patient_id'                  => $this->patient_id,
            'provider_id'                 => $this->provider_id,
            'appointment_id'              => $this->appointment_id,
            'department_id'               => $this->department_id,
            'encounter_type'              => $this->encounter_type,
            'status'                      => $this->status,
            'started_at'                  => $this->started_at?->toIso8601String(),
            'ended_at'                    => $this->ended_at?->toIso8601String(),
            'chief_complaint'             => $this->chief_complaint,
            'history_of_present_illness'  => $this->history_of_present_illness,
            'examination_findings'        => $this->examination_findings,
            'assessment'                  => $this->assessment,
            'plan'                        => $this->plan,
            'advice_to_patient'           => $this->advice_to_patient,
            'clinical_summary'            => $this->clinical_summary,
            'follow_up_date'              => $this->follow_up_date?->toDateString(),

            'patient' => $this->whenLoaded('patient', fn () => [
                'id'         => $this->patient->id,
                'uhid'       => $this->patient->uhid,
                'first_name' => $this->patient->first_name,
                'last_name'  => $this->patient->last_name,
                'full_name'  => trim("{$this->patient->first_name} {$this->patient->last_name}"),
                'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
                'gender'     => $this->patient->gender,
                'phone'      => $this->patient->phone,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => [
                'id'   => $this->provider->id,
                'user' => $this->provider->user ? [
                    'id'   => $this->provider->user->id,
                    'name' => $this->provider->user->name,
                ] : null,
                'specialization' => $this->provider->specialization,
            ]),
            'observations'  => ObservationResource::collection($this->whenLoaded('observations')),
            'conditions'    => ConditionResource::collection($this->whenLoaded('conditions')),
            'prescriptions' => PrescriptionResource::collection($this->whenLoaded('prescriptions')),
        ];
    }
}
