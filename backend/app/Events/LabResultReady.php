<?php

namespace App\Events;

use App\Models\Laboratory\LabOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an entire LabOrder completes (every component test has a
 * verified result). The patient and the ordering doctor are notified;
 * the patient's chart timeline updates in real time.
 */
class LabResultReady
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LabOrder $order)
    {
    }
}
