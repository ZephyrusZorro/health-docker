<?php

namespace App\Events;

use App\Models\Billing\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Invoice $invoice)
    {
    }
}
