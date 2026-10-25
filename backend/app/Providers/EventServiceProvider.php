<?php

namespace App\Providers;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\LabResultReady;
use App\Events\MedicineDispensed;
use App\Events\PatientRegistered;
use App\Events\QueueUpdated;
use App\Events\StockLow;
use App\Listeners\DispatchAppointmentReminders;
use App\Listeners\LogAuditEvent;
use App\Listeners\NotifyLowStockAlert;
use App\Listeners\NotifyPatientOfBooking;
use App\Listeners\NotifyPatientOfResult;
use App\Listeners\RecalculateInvoice;
use App\Listeners\UpdateStockLedger;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event-driven internal architecture (SRS §6.2 Architecture Style).
 * Every domain event fan-outs into audit logs and, where relevant, notifications.
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PatientRegistered::class => [
            LogAuditEvent::class,
        ],
        AppointmentBooked::class => [
            LogAuditEvent::class,
            NotifyPatientOfBooking::class,
            DispatchAppointmentReminders::class,
        ],
        AppointmentCancelled::class => [
            LogAuditEvent::class,
        ],
        QueueUpdated::class => [
            // Broadcast-only, no listener needed (ShouldBroadcast on the event).
        ],
        MedicineDispensed::class => [
            LogAuditEvent::class,
            UpdateStockLedger::class,
            RecalculateInvoice::class,
        ],
        StockLow::class => [
            NotifyLowStockAlert::class,
        ],
        LabResultReady::class => [
            LogAuditEvent::class,
            NotifyPatientOfResult::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
