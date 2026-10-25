<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyPatientOfBooking implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $notifier)
    {
    }

    public function handle(AppointmentBooked $event): void
    {
        $appt    = $event->appointment->loadMissing(['patient', 'provider.user']);
        $patient = $appt->patient;
        $doctor  = $appt->provider?->user?->name ?? 'your doctor';

        $this->notifier->dispatch(
            templateKey: 'appointment.confirmed',
            recipient: [
                'name'  => $patient->full_name,
                'phone' => $patient->phone,
                'email' => $patient->email,
            ],
            payload: [
                'patient_name'         => $patient->first_name,
                'doctor_name'          => $doctor,
                'appointment_datetime' => $appt->scheduled_start?->format('d M Y, h:i A'),
                'token_number'         => $appt->token_number,
                'appointment_number'   => $appt->appointment_number,
                'facility_name'        => $appt->facility?->name ?? config('app.name'),
            ],
            channels: array_filter(['email', $patient->phone ? 'sms' : null]),
        );
    }
}
