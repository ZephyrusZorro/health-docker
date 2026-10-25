<?php

namespace App\Policies;

use App\Models\Identity\User;
use App\Models\Scheduling\Appointment;

/**
 * AppointmentPolicy — gates state transitions on the appointment lifecycle.
 *
 * Each ability is permission-driven so admins can adjust who can do what
 * via the role matrix. The route middleware also enforces a coarser check
 * (e.g. `permission:appointments.update`) — this policy adds the relational
 * guard "is this provider's own appointment".
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view') || $user->hasAnyRole(['receptionist', 'doctor', 'nurse', 'hospital_admin', 'super_admin']);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('appointments.create')
            || $user->hasAnyRole(['receptionist', 'hospital_admin', 'super_admin']);
    }

    public function checkIn(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.update')
            || $user->hasAnyRole(['receptionist', 'nurse', 'hospital_admin', 'super_admin']);
    }

    public function startConsult(User $user, Appointment $appointment): bool
    {
        if ($user->hasAnyRole(['hospital_admin', 'super_admin'])) return true;
        // The doctor on the appointment is the only clinician who can start.
        if ($user->provider && $user->provider->id === $appointment->provider_id) return true;
        return $user->can('emr.consult');
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->startConsult($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.cancel')
            || $user->hasAnyRole(['receptionist', 'doctor', 'hospital_admin', 'super_admin']);
    }

    public function markNoShow(User $user, Appointment $appointment): bool
    {
        return $this->cancel($user, $appointment);
    }
}
