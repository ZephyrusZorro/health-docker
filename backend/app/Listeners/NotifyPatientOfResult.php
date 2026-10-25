<?php

namespace App\Listeners;

use App\Events\LabResultReady;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyPatientOfResult implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $notifier)
    {
    }

    public function handle(LabResultReady $event): void
    {
        $order   = $event->order->loadMissing(['patient', 'tests.labTest']);
        $patient = $order->patient;
        if (! $patient) return;

        $testNames = $order->tests
            ->map(fn ($t) => $t->test_name ?? $t->labTest?->name)
            ->filter()->take(5)->implode(', ');

        $this->notifier->dispatch(
            templateKey: 'lab.result_ready',
            recipient: [
                'name'  => trim("{$patient->first_name} {$patient->last_name}"),
                'phone' => $patient->phone,
                'email' => $patient->email,
            ],
            payload: [
                'patient_name' => $patient->first_name,
                'order_number' => $order->lab_order_number,
                'test_names'   => $testNames,
            ],
            channels: array_filter(['email', $patient->phone ? 'sms' : null]),
        );
    }
}
