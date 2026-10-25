<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'provider_type'        => $this->provider_type,
            'specialization'       => $this->specialization,
            'qualification'        => $this->qualification,
            'registration_number'  => $this->registration_number,
            'years_of_experience'  => $this->years_of_experience,
            'consultation_fee'     => $this->consultation_fee,
            'bio'                  => $this->bio,
            'languages'            => $this->languages,
            'is_available_online'  => $this->is_available_online,
            'user'                 => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'phone'  => $this->user->phone,
                'avatar_url' => $this->user->avatar_url,
            ]),
            'department'           => $this->whenLoaded('department', fn () => [
                'id'   => $this->department?->id,
                'name' => $this->department?->name,
            ]),
        ];
    }
}
