<?php

namespace App\Policies;

use App\Models\Identity\Patient;
use App\Models\Identity\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view')
            || $user->hasAnyRole(['receptionist', 'doctor', 'nurse', 'pharmacist', 'lab_technician', 'cashier', 'hospital_admin', 'super_admin']);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('patients.create')
            || $user->hasAnyRole(['receptionist', 'doctor', 'hospital_admin', 'super_admin']);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can('patients.update')
            || $user->hasAnyRole(['receptionist', 'doctor', 'hospital_admin', 'super_admin']);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('patients.delete')
            || $user->hasAnyRole(['hospital_admin', 'super_admin']);
    }

    public function merge(User $user, Patient $patient): bool
    {
        return $user->can('patients.merge')
            || $user->hasAnyRole(['hospital_admin', 'super_admin']);
    }
}
