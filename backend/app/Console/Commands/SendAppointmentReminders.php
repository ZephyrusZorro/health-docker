<?php

namespace App\Console\Commands;

use App\Models\Scheduling\Appointment;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Find appointments starting in the next reminder window (configurable —
 * defaults to "starts between 60 and 70 minutes from now") and dispatch
 * a reminder notification. The 10-minute overlap is intentional: each
 * run covers the following 10 minutes, so two consecutive runs never
 * leave a gap or send duplicates.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'hms:send-appointment-reminders
                            {--offset=60 : Remind this many minutes before start}';

    protected $description = 'Send reminder notifications for upcoming appointments';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $offset = (int) $this->option('offset');
        $from = Carbon::now()->addMinutes($offset);
        $to   = $from->copy()->addMinutes(10);

        $appointments = Appointment::with(['patient', 'provider.user'])
            ->whereIn('status', ['booked', 'confirmed'])
            ->whereBetween('scheduled_start', [$from, $to])
            ->whereNull('reminder_sent_at')
            ->get();

        $this->info("Found {$appointments->count()} appointment(s) to remind.");

        $sent = 0;
        foreach ($appointments as $appt) {
            try {
                $dispatcher->send(
                    template: 'appointment.reminder',
                    recipient: $appt->patient,
                    data: [
                        'patient_name'   => $appt->patient->full_name,
                        'doctor_name'    => $appt->provider->user->name,
                        'scheduled_at'   => $appt->scheduled_start->format('D, M j · h:i A'),
                        'token'          => $appt->token_number,
                        'appointment_no' => $appt->appointment_number,
                    ],
                    channels: ['sms', 'email'],
                );
                $appt->update(['reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("  · Reminder failed for #{$appt->id}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} reminder(s).");
        return self::SUCCESS;
    }
}
