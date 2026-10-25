<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Identity\Patient::class       => \App\Policies\PatientPolicy::class,
        \App\Models\Identity\Provider::class      => \App\Policies\ProviderPolicy::class,
        \App\Models\Identity\Department::class    => \App\Policies\DepartmentPolicy::class,
        \App\Models\Clinical\Medication::class    => \App\Policies\MedicationPolicy::class,
        \App\Models\Scheduling\Appointment::class => \App\Policies\AppointmentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Super admin bypass — Super Admin role (SRS §3) skips all gate checks.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
