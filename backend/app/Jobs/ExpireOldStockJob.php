<?php

namespace App\Jobs;

use App\Models\Pharmacy\MedicineBatch;
use App\Models\Pharmacy\StockMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Nightly job: flag expired batches, write off remaining stock to the ledger.
 * SRS §4.7 Business Rule: expired stock cannot be dispensed.
 */
class ExpireOldStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        MedicineBatch::where('expiry_date', '<', now())
            ->where('current_quantity', '>', 0)
            ->chunkById(200, function ($batches) {
                DB::transaction(function () use ($batches) {
                    foreach ($batches as $batch) {
                        $qty = $batch->current_quantity;
                        $batch->update(['current_quantity' => 0]);

                        StockMovement::create([
                            'facility_id'       => $batch->facility_id,
                            'medicine_batch_id' => $batch->id,
                            'medication_id'     => $batch->medication_id,
                            'performed_by'      => null, // system-initiated
                            'movement_type'     => 'expiry_writeoff',
                            'quantity'          => $qty,
                            'balance_after'     => 0,
                            'reason'            => "Auto write-off on expiry ({$batch->expiry_date->format('Y-m-d')})",
                        ]);
                    }
                });
            });
    }
}
