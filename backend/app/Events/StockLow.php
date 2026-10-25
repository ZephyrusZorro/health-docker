<?php

namespace App\Events;

use App\Models\Clinical\Medication;
use App\Models\Identity\Facility;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Facility $facility,
        public readonly Medication $medication,
        public readonly int $currentStock,
        public readonly int $reorderLevel,
    ) {
    }
}
