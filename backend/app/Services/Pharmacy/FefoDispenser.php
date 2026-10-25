<?php

namespace App\Services\Pharmacy;

use App\Events\MedicineDispensed;
use App\Events\StockLow;
use App\Models\Clinical\Medication;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Pharmacy\Dispense;
use App\Models\Pharmacy\DispenseItem;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Pharmacy\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dispensing engine implementing FEFO — First Expiry First Out (SRS §4.7).
 *
 * Given a medication and quantity, this service picks batches in ascending
 * expiry order, decrements their stock within a DB transaction, writes a
 * stock_movements row per batch, and emits StockLow events when a
 * medication's aggregate stock drops below its reorder level.
 *
 * Critical guarantees:
 *  - Expired batches are NEVER selected (SRS §4.7 Business Rule).
 *  - Negative stock is impossible (transaction-scoped lock + check).
 *  - Every movement is auditable (source-linked to the dispense row).
 */
class FefoDispenser
{
    /**
     * @param array<array{medication_id:int, quantity:int, prescription_item_id?:int|null, instructions?:string|null}> $items
     */
    public function dispense(
        Facility $facility,
        Patient $patient,
        array $items,
        ?int $prescriptionId = null,
        ?int $encounterId = null,
    ): Dispense {
        if (empty($items)) {
            throw new \InvalidArgumentException('Dispense must include at least one item.');
        }

        return DB::transaction(function () use ($facility, $patient, $items, $prescriptionId) {
            $userId = Auth::id() ?? \App\Models\Identity\User::value('id');

            $dispense = Dispense::create([
                'facility_id'     => $facility->id,
                'patient_id'      => $patient->id,
                'prescription_id' => $prescriptionId,
                'pharmacist_id'   => $userId,
                'dispense_type'   => $prescriptionId ? 'prescription' : 'otc',
                'dispensed_at'    => now(),
                'status'          => 'completed',
                'dispense_number' => $this->nextDispenseNumber($facility),
                'subtotal'        => 0,
                'tax_amount'      => 0,
                'discount_amount' => 0,
                'total_amount'    => 0,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($items as $item) {
                $medication = Medication::findOrFail($item['medication_id']);
                $remaining  = (int) $item['quantity'];

                if ($remaining <= 0) {
                    throw new \InvalidArgumentException("Quantity must be positive for medication {$medication->id}.");
                }

                // FEFO selection: non-expired in-stock batches, earliest expiry first.
                $batches = MedicineBatch::query()
                    ->where('facility_id', $facility->id)
                    ->where('medication_id', $medication->id)
                    ->where('current_quantity', '>', 0)
                    ->where('expiry_date', '>', now())
                    ->orderBy('expiry_date')
                    ->lockForUpdate()
                    ->get();

                if ($batches->isEmpty() || $batches->sum('current_quantity') < $remaining) {
                    throw new RuntimeException(
                        "Insufficient stock for medication '{$medication->displayName()}'. "
                        . "Required: {$remaining}, available: " . $batches->sum('current_quantity')
                    );
                }

                foreach ($batches as $batch) {
                    if ($remaining <= 0) break;

                    $take      = min($remaining, $batch->current_quantity);
                    $unitPrice = (float) $batch->selling_price;
                    $taxPct    = (float) $batch->tax_percent;
                    $lineSub   = $take * $unitPrice;
                    $lineTax   = round($lineSub * $taxPct / 100, 2);
                    $lineTotal = round($lineSub + $lineTax, 2);

                    DispenseItem::create([
                        'dispense_id'          => $dispense->id,
                        'prescription_item_id' => $item['prescription_item_id'] ?? null,
                        'medicine_batch_id'    => $batch->id,
                        'medication_id'        => $medication->id,
                        'medication_name'      => $medication->displayName(),
                        'batch_number'         => $batch->batch_number,
                        'expiry_date'          => $batch->expiry_date,
                        'quantity'             => $take,
                        'unit_price'           => $unitPrice,
                        'tax_percent'          => $taxPct,
                        'tax_amount'           => $lineTax,
                        'discount_amount'      => 0,
                        'line_total'           => $lineTotal,
                        'is_substituted'       => false,
                    ]);

                    $batch->decrement('current_quantity', $take);
                    $batch->refresh();

                    StockMovement::create([
                        'facility_id'       => $facility->id,
                        'medicine_batch_id' => $batch->id,
                        'medication_id'     => $medication->id,
                        'performed_by'      => $userId,
                        'movement_type'     => 'dispense',
                        'quantity'          => $take,
                        'balance_after'     => $batch->current_quantity,
                        'source_type'       => Dispense::class,
                        'source_id'         => $dispense->id,
                        'reason'            => "Dispensed to patient {$patient->uhid}",
                    ]);

                    $remaining -= $take;
                    $subtotal  += $lineSub;
                    $taxTotal  += $lineTax;
                }

                $this->checkLowStock($facility, $medication);
            }

            $dispense->update([
                'subtotal'     => round($subtotal, 2),
                'tax_amount'   => round($taxTotal, 2),
                'total_amount' => round($subtotal + $taxTotal, 2),
            ]);
            $dispense->refresh();

            event(new MedicineDispensed($dispense));
            return $dispense;
        });
    }

    private function checkLowStock(Facility $facility, Medication $medication): void
    {
        $total = (int) MedicineBatch::where('facility_id', $facility->id)
            ->where('medication_id', $medication->id)
            ->where('expiry_date', '>', now())
            ->sum('current_quantity');

        $reorderLevel = (int) ($medication->reorder_level ?? 10);
        if ($total <= $reorderLevel) {
            event(new StockLow(
                facility:     $facility,
                medication:   $medication,
                currentStock: $total,
                reorderLevel: $reorderLevel,
            ));
        }
    }

    private function nextDispenseNumber(Facility $facility): string
    {
        $year   = now()->year;
        $month  = now()->format('m');
        $count  = Dispense::where('facility_id', $facility->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', (int) $month)
            ->count();
        $seq    = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
        return sprintf('DSP-%d%s-%s', $year, $month, $seq);
    }
}
