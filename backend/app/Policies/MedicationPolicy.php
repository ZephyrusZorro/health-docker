<?php

namespace App\Policies;

use App\Models\Clinical\Medication;
use App\Models\Identity\User;

class MedicationPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Medication $medication): bool { return true; }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['pharmacist', 'hospital_admin', 'super_admin']);
    }

    public function update(User $user, Medication $medication): bool
    {
        return $user->hasAnyRole(['pharmacist', 'hospital_admin', 'super_admin']);
    }

    public function delete(User $user, Medication $medication): bool
    {
        return $user->hasAnyRole(['hospital_admin', 'super_admin']);
    }
}
