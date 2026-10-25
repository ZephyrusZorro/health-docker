<?php

namespace App\Policies;

use App\Models\Identity\Provider;
use App\Models\Identity\User;

class ProviderPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Provider $provider): bool { return true; }

    public function update(User $user, Provider $provider): bool
    {
        if ($user->hasAnyRole(['hospital_admin', 'super_admin'])) return true;
        return $user->provider && $user->provider->id === $provider->id;
    }
}
