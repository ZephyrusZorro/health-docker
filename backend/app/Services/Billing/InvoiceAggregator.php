<?php

namespace App\Services\Billing;

use App\Models\Admission\Admission;
use App\Models\Admission\BedAssignment;
use App\Models\Billing\Invoice;
use App\Models\Billing\InvoiceItem;
use App\Models\Clinical\Encounter;
use App\Models\Laboratory\LabOrder;
use App\Models\Laboratory\LabOrderTest;
use App\Models\Pharmacy\Dispense;
use App\Models\Pharmacy\DispenseItem;
use Illuminate\Support\Facades\DB;

/**
 * Invoice aggregator — SRS §4.9.
 *
 * Every billable event across OPD, IPD, pharmacy, and laboratory is
 * automatically aggregated into a unified patient invoice. Every line item
 * is source-linked (polymorphic source_type / source_id) to its originating
 * service or order — this is a non-negotiable Business Rule.
 *
 * Usage:
 *   $invoice = $aggregator->createOrUpdateForEncounter($encounter);
 *   $invoice = $aggregator->createOrUpdateForAdmission($admission);
 *   $aggregator->recalculateTotals($invoice);
 */
class InvoiceAggregator
{
    /**
     * Aggregate all charges from an OPD encounter into a single invoice.
     * Idempotent — re-runnable without duplicating items (firstOrCreate by
     * source_type + source_id).
     */
    public function createOrUpdateForEncounter(Encounter $encounter): Invoice
    {
        return DB::transaction(function () use ($encounter) {
            $invoice = Invoice::firstOrCreate(
                ['encounter_id' => $encounter->id, 'admission_id' => null],
                [
                    'invoice_number' => $this->nextInvoiceNumber($encounter->facility_id),
                    'facility_id'    => $encounter->facility_id,
                    'patient_id'     => $encounter->patient_id,
                    'created_by'     => auth()->id() ?? \App\Models\Identity\User::value('id'),
                    'invoice_type'   => 'opd',
                    'status'         => Invoice::STATUS_DRAFT,
                    'invoice_date'   => now()->toDateString(),
                    'currency'       => config('hms.billing.default_currency', 'INR'),
                ]
            );

            // 1. Consultation fee — sourced from Encounter itself
            $provider = $encounter->provider()->with('user')->first();
            if ($provider && (float) $provider->consultation_fee > 0) {
                $this->ensureItem($invoice, 'consultation', $encounter, [
                    'item_name'  => "Consultation — Dr. " . ($provider->user?->name ?? '—'),
                    'quantity'   => 1,
                    'unit_price' => (float) $provider->consultation_fee,
                ]);
            }

            // 2. Pharmacy dispenses tied to this encounter (via prescription)
            //    Dispense has prescription_id → Prescription has encounter_id.
            Dispense::whereHas('prescription', fn ($q) => $q->where('encounter_id', $encounter->id))
                ->with(['items'])
                ->each(function (Dispense $dispense) use ($invoice) {
                    foreach ($dispense->items as $line) {
                        $this->ensureItem($invoice, 'pharmacy', $line, [
                            'item_name'   => $line->medication_name ?: 'Medicine',
                            'quantity'    => (int) $line->quantity,
                            'unit_price'  => (float) $line->unit_price,
                            'tax_percent' => (float) $line->tax_percent,
                        ]);
                    }
                });

            // 3. Lab orders placed during this encounter
            LabOrder::where('encounter_id', $encounter->id)
                ->with('tests')
                ->each(function (LabOrder $order) use ($invoice) {
                    foreach ($order->tests as $line) {
                        $this->ensureItem($invoice, 'laboratory', $line, [
                            'item_name'  => $line->test_name ?: 'Lab test',
                            'quantity'   => 1,
                            'unit_price' => (float) $line->price_snapshot,
                        ]);
                    }
                });

            $this->recalculateTotals($invoice);
            return $invoice->fresh(['items']);
        });
    }

    /**
     * Aggregate all IPD charges (bed days, consultations, pharmacy, lab)
     * into one final admission invoice.
     */
    public function createOrUpdateForAdmission(Admission $admission): Invoice
    {
        return DB::transaction(function () use ($admission) {
            $invoice = Invoice::firstOrCreate(
                ['admission_id' => $admission->id],
                [
                    'invoice_number' => $this->nextInvoiceNumber($admission->facility_id),
                    'facility_id'    => $admission->facility_id,
                    'patient_id'     => $admission->patient_id,
                    'encounter_id'   => null,
                    'created_by'     => auth()->id() ?? \App\Models\Identity\User::value('id'),
                    'invoice_type'   => 'ipd',
                    'status'         => Invoice::STATUS_DRAFT,
                    'invoice_date'   => now()->toDateString(),
                    'currency'       => config('hms.billing.default_currency', 'INR'),
                ]
            );

            // 1. Bed charges — one line per BedAssignment (transfers split bills nicely)
            BedAssignment::where('admission_id', $admission->id)
                ->with('bed.room')
                ->get()
                ->each(function (BedAssignment $assign) use ($invoice) {
                    $start = $assign->assigned_at;
                    $end   = $assign->vacated_at ?? now();
                    $hours = max(0.0, (float) $start->diffInHours($end, true));
                    $days  = max(1, (int) ceil($hours / 24));

                    $rate = (float) ($assign->daily_rate_snapshot ?? $assign->bed?->room?->daily_rate ?? 0);
                    if ($rate <= 0) return;

                    $this->ensureItem($invoice, 'bed_charge', $assign, [
                        'item_name'  => "Bed " . ($assign->bed?->bed_number ?? '') . " × {$days} day(s)",
                        'quantity'   => $days,
                        'unit_price' => $rate,
                    ]);
                });

            // 2. Pharmacy + 3. Lab — fold in any encounters owned by this admission's patient
            //    while admitted (date range bound).
            $start = $admission->admitted_at;
            $end   = $admission->discharged_at ?? now();

            // Pharmacy: every dispense for this patient within admission window
            Dispense::where('patient_id', $admission->patient_id)
                ->whereBetween('dispensed_at', [$start, $end])
                ->with('items')
                ->each(function (Dispense $d) use ($invoice) {
                    foreach ($d->items as $line) {
                        $this->ensureItem($invoice, 'pharmacy', $line, [
                            'item_name'   => $line->medication_name ?: 'Medicine',
                            'quantity'    => (int) $line->quantity,
                            'unit_price'  => (float) $line->unit_price,
                            'tax_percent' => (float) $line->tax_percent,
                        ]);
                    }
                });

            // Laboratory: every lab order for the patient in the admission window
            LabOrder::where('patient_id', $admission->patient_id)
                ->whereBetween('ordered_at', [$start, $end])
                ->with('tests')
                ->each(function (LabOrder $o) use ($invoice) {
                    foreach ($o->tests as $line) {
                        $this->ensureItem($invoice, 'laboratory', $line, [
                            'item_name'  => $line->test_name ?: 'Lab test',
                            'quantity'   => 1,
                            'unit_price' => (float) $line->price_snapshot,
                        ]);
                    }
                });

            $this->recalculateTotals($invoice);
            return $invoice->fresh(['items']);
        });
    }

    /**
     * Insert a line item for a source record if one doesn't already exist.
     * Polymorphic: source_type = FQCN, source_id = PK.
     */
    private function ensureItem(Invoice $invoice, string $category, $source, array $attrs): void
    {
        $qty       = (int) ($attrs['quantity']    ?? 1);
        $unitPrice = (float) ($attrs['unit_price'] ?? 0);
        $taxPct    = (float) ($attrs['tax_percent'] ?? 0);

        $subtotal = $qty * $unitPrice;
        $tax      = round($subtotal * ($taxPct / 100), 2);
        $line     = round($subtotal + $tax, 2);

        InvoiceItem::firstOrCreate(
            [
                'invoice_id'  => $invoice->id,
                'source_type' => $source::class,
                'source_id'   => $source->getKey(),
            ],
            [
                'item_name'        => $attrs['item_name'] ?? 'Item',
                'item_code'        => $attrs['item_code'] ?? null,
                'category'         => $category,
                'quantity'         => $qty,
                'unit_price'       => $unitPrice,
                'discount_percent' => 0,
                'discount_amount'  => 0,
                'tax_percent'      => $taxPct,
                'tax_amount'       => $tax,
                'line_total'       => $line,
            ]
        );
    }

    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $items = $invoice->items()->get();

        $subtotal = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) $i->unit_price);
        $taxAmt   = (float) $items->sum(fn ($i) => (float) $i->tax_amount);
        $discount = (float) ($invoice->discount_amount ?? 0);
        $total    = round($subtotal + $taxAmt - $discount, 2);

        $paid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
        $refunded = (float) $invoice->refunded_amount;
        $balance  = round($total - $paid + $refunded, 2);

        $status = $invoice->status;
        if ($status !== Invoice::STATUS_CANCELLED && $status !== Invoice::STATUS_DRAFT) {
            if ($balance <= 0.01)        $status = Invoice::STATUS_PAID;
            elseif ($paid > 0)           $status = Invoice::STATUS_PARTIAL;
            else                         $status = Invoice::STATUS_PENDING;
        }

        $invoice->update([
            'subtotal'       => round($subtotal, 2),
            'tax_amount'     => round($taxAmt, 2),
            'total_amount'   => $total,
            'paid_amount'    => round($paid, 2),
            'balance_amount' => $balance,
            'status'         => $status,
        ]);

        return $invoice->refresh();
    }

    private function nextInvoiceNumber(int $facilityId): string
    {
        $year  = now()->year;
        $month = now()->format('m');
        $count = Invoice::where('facility_id', $facilityId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', (int) $month)
            ->count();
        $seq = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
        return sprintf('INV-%d%s-%s', $year, $month, $seq);
    }
}
