<?php

namespace App\Listeners;

use App\Events\MedicineDispensed;
use App\Services\Billing\InvoiceAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Whenever a dispense occurs that's tied to a prescription belonging to an
 * encounter, refresh the patient's invoice so totals stay current. (The
 * dispense → prescription → encounter chain is how we link OTC-style POS
 * sales away from invoices, while keeping prescribed dispenses billable.)
 */
class RecalculateInvoice implements ShouldQueue
{
    public function __construct(private readonly InvoiceAggregator $aggregator)
    {
    }

    public function handle(MedicineDispensed $event): void
    {
        $dispense = $event->dispense->loadMissing('prescription.encounter');
        $encounter = $dispense->prescription?->encounter;

        if ($encounter) {
            $this->aggregator->createOrUpdateForEncounter($encounter);
        }
    }
}
