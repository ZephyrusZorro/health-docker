<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Jobs\SendAppointmentReminder;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Schedules reminder jobs at the configured offsets before the appointment.
 */
class DispatchAppointmentReminders implements ShouldQueue
{
    public function handle(AppointmentBooked $event): void
    {
        $appt = $event->appointment;
        $hours = config('hms.scheduling.reminder_hours', [24, 2]);

        foreach ($hours as $h) {
            $fireAt = $appt->scheduled_start?->copy()->subHours((int) $h);
            if ($fireAt && $fireAt->isFuture()) {
                SendAppointmentReminder::dispatch($appt->id, (int) $h)->delay($fireAt);
            }
        }
    }
}
