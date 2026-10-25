<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Always include roles + permissions if relations are loaded; the
        // frontend's auth context relies on these for can()/hasRole().
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'avatar_url'      => $this->avatar_url,
            'user_type'       => $this->user_type,
            'is_active'       => (bool) $this->is_active,
            'mfa_enabled'     => (bool) ($this->mfa_enabled ?? false),
            'last_login_at'   => $this->last_login_at?->toIso8601String(),
            'facility_id'     => $this->facility_id,

            'facility' => $this->whenLoaded('facility', fn () => $this->facility ? [
                'id'   => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ] : null),

            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id'              => $this->provider->id,
                'provider_type'   => $this->provider->provider_type,
                'specialization'  => $this->provider->specialization,
                'department_id'   => $this->provider->department_id,
                'consultation_fee'=> $this->provider->consultation_fee,
            ] : null),

            'staff' => $this->whenLoaded('staff', fn () => $this->staff ? [
                'id'           => $this->staff->id,
                'designation'  => $this->staff->designation,
                'department_id'=> $this->staff->department_id,
            ] : null),

            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => [
                'id'           => $r->id,
                'name'         => $r->name,
                'display_name' => $r->display_name ?? ucwords(str_replace('_', ' ', $r->name)),
            ])->values()),

            'permissions' => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()
                ->map(fn ($p) => ['name' => $p->name])
                ->values()),
        ];
    }
}
