<?php

namespace App\Policies;

use App\Models\Identity\Department;
use App\Models\Identity\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Department $department): bool { return true; }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['hospital_admin', 'super_admin']);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['hospital_admin', 'super_admin']);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['hospital_admin', 'super_admin']);
    }
}
