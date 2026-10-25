<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', fn () => $this->comment(Inspiring::quote()))
       ->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks — SRS §4.13 "event-driven notification engine"
|--------------------------------------------------------------------------
*/

// Daily slot generation — create the next 14 days of slots for every
// active provider from their schedule template.
Schedule::command('hms:generate-slots --days=14')->dailyAt('02:00');

// Appointment reminder sweep — fires 24h and 2h before each appointment.
Schedule::command('hms:send-appointment-reminders')->everyTenMinutes();

// Low-stock / expiry alerts for pharmacy.
Schedule::command('hms:pharmacy-alerts')->dailyAt('07:00');

// Bed "cleaning → available" transition.
Schedule::command('hms:bed-cleanup-check')->everyFifteenMinutes();

// Nightly audit log archive (partition rotation).
Schedule::command('hms:audit-rotate')->dailyAt('03:15');
