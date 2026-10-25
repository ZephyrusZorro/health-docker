<?php

namespace App\Listeners;

use App\Events\MedicineDispensed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The stock_movements rows are written inside FefoDispenser's transaction,
 * so this listener is a no-op at runtime — kept as an extension point for
 * downstream concerns (warehouse replication, external inventory sync).
 */
class UpdateStockLedger implements ShouldQueue
{
    public function handle(MedicineDispensed $event): void
    {
        // Hook: future warehouse/ERP sync, analytics materialised view refresh, etc.
    }
}
