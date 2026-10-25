<?php

namespace App\Jobs;

use App\Models\Scheduling\Appointment;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delayed job dispatched by DispatchAppointmentReminders at 24h and 2h marks.
 * Guards against sending a reminder if the appointment was cancelled or
 * already checked-in between scheduling and firing.
 */
class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $appointmentId,
        public readonly int $hoursBefore,
    ) {
    }

    public function handle(NotificationDispatcher $notifier): void
    {
        $appt = Appointment::with(['patient', 'provider.user'])->find($this->appointmentId);
        if (! $appt || in_array($appt->status, [
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_IN_CONSULT,
        ], true)) {
            return; // No-op — user already moved forward or is cancelled.
        }

        $p = $appt->patient;
        $notifier->dispatch(
            templateKey: 'appointment.reminder',
            recipient: ['name' => $p->full_name, 'phone' => $p->phone, 'email' => $p->email],
            payload: [
                'patient_name'   => $p->first_name,
                'doctor_name'    => $appt->provider?->user?->name,
                'scheduled_at'   => $appt->scheduled_start?->format('d M Y, h:i A'),
                'token'          => $appt->token_number,
                'hours_before'   => $this->hoursBefore,
            ],
            channels: array_filter(['email', $p->phone ? 'sms' : null]),
        );
    }
}
