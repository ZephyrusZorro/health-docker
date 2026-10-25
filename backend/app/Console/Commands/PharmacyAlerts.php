<?php

namespace App\Console\Commands;

use App\Events\StockLow;
use App\Models\Clinical\Medication;
use App\Models\Identity\Facility;
use App\Models\Identity\User;
use App\Models\Pharmacy\MedicineBatch;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily pharmacy surveillance: flags medicines below reorder_level
 * (aggregated across non-expired in-stock batches per facility) and
 * flags batches expiring within the configured window. Emits StockLow
 * events and a digest email to all pharmacists.
 *
 * SRS reference: §4.7 Pharmacy + §4.13 Notifications & Automation.
 */
class PharmacyAlerts extends Command
{
    protected $signature = 'hms:pharmacy-alerts
                            {--expiry-days=60 : Flag batches expiring within N days}';

    protected $description = 'Daily pharmacy low-stock and expiring-batch alerts';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $expiryDays = (int) $this->option('expiry-days');

        // --- Low-stock per (facility, medication) ----------------------------
        $lowStockRows = DB::table('medicine_batches as mb')
            ->join('medications as m', 'm.id', '=', 'mb.medication_id')
            ->join('facilities as f', 'f.id', '=', 'mb.facility_id')
            ->where('mb.expiry_date', '>', now())
            ->whereNull('mb.deleted_at')
            ->select(
                'm.id as medication_id',
                'm.generic_name',
                'm.brand_name',
                'm.reorder_level',
                'f.id as facility_id',
                'f.code as facility_code',
                DB::raw('COALESCE(SUM(mb.current_quantity), 0) as total_stock'),
            )
            ->groupBy('m.id', 'm.generic_name', 'm.brand_name', 'm.reorder_level', 'f.id', 'f.code')
            ->havingRaw('COALESCE(SUM(mb.current_quantity), 0) <= m.reorder_level')
            ->get();

        $this->info("Low-stock medications: {$lowStockRows->count()}");

        foreach ($lowStockRows as $row) {
            $facility   = Facility::find($row->facility_id);
            $medication = Medication::find($row->medication_id);
            if (! $facility || ! $medication) continue;

            event(new StockLow(
                facility:     $facility,
                medication:   $medication,
                currentStock: (int) $row->total_stock,
                reorderLevel: (int) $row->reorder_level,
            ));
        }

        // --- Expiring batches ----------------------------------------------
        $expiring = MedicineBatch::with(['medication', 'facility'])
            ->where('current_quantity', '>', 0)
            ->whereBetween('expiry_date', [now(), now()->addDays($expiryDays)])
            ->get();

        $this->info("Expiring batches (≤ {$expiryDays}d): {$expiring->count()}");

        if ($lowStockRows->isEmpty() && $expiring->isEmpty()) {
            return self::SUCCESS;
        }

        // --- Digest to pharmacists -----------------------------------------
        $pharmacists = User::role('pharmacist')->where('is_active', true)->get();

        foreach ($pharmacists as $user) {
            try {
                $dispatcher->send(
                    template:  'pharmacy.daily_digest',
                    recipient: $user,
                    data: [
                        'low_stock_count' => $lowStockRows->count(),
                        'expiring_count'  => $expiring->count(),
                        'low_stock_items' => $lowStockRows->take(10)->map(fn ($r) => [
                            'name'     => $r->brand_name ?: $r->generic_name,
                            'stock'    => (int) $r->total_stock,
                            'reorder'  => (int) $r->reorder_level,
                            'facility' => $r->facility_code,
                        ])->toArray(),
                        'expiring_items' => $expiring->take(10)->map(fn ($b) => [
                            'name'   => $b->medication?->displayName() ?? '—',
                            'batch'  => $b->batch_number,
                            'expiry' => $b->expiry_date->toDateString(),
                            'qty'    => $b->current_quantity,
                        ])->toArray(),
                    ],
                    channels: ['email'],
                );
            } catch (\Throwable $e) {
                $this->warn("  · Digest failed for {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info('Pharmacy alerts dispatched.');
        return self::SUCCESS;
    }
}
