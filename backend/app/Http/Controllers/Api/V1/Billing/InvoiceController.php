<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\InvoiceResource;
use App\Models\Billing\Invoice;
use App\Services\Billing\InvoiceAggregator;
use Illuminate\Http\Request;

class InvoiceController extends ApiController
{
    public function __construct(private readonly InvoiceAggregator $aggregator)
    {
    }

    public function index(Request $request)
    {
        $q = Invoice::query()
            ->with(['patient', 'items'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->patient_id))
            ->when($request->filled('status'),     fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('from'),       fn ($q) => $q->whereDate('invoice_date', '>=', $request->from))
            ->when($request->filled('to'),         fn ($q) => $q->whereDate('invoice_date', '<=', $request->to))
            ->when($request->boolean('unpaid_only'), fn ($q) => $q->where('balance_amount', '>', 0))
            ->latest('invoice_date');

        return InvoiceResource::collection($q->paginate($request->integer('per_page', 20)));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['patient', 'items', 'payments']);
        return $this->ok(new InvoiceResource($invoice));
    }

    /** Re-aggregate an invoice — useful after late-arriving charges. */
    public function refresh(Invoice $invoice)
    {
        if ($invoice->encounter_id) {
            $this->aggregator->createOrUpdateForEncounter($invoice->encounter);
        } elseif ($invoice->admission_id) {
            $this->aggregator->createOrUpdateForAdmission($invoice->admission);
        } else {
            $this->aggregator->recalculateTotals($invoice);
        }
        return $this->ok(
            new InvoiceResource($invoice->fresh(['patient', 'items', 'payments'])),
            'Recalculated',
        );
    }

    /**
     * Finalise: moves an invoice from draft → pending; after this, items
     * shouldn't change (enforced in service / future policy).
     */
    public function finalise(Invoice $invoice)
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return $this->fail('Only draft invoices can be finalised.', 422);
        }
        $invoice->update(['status' => Invoice::STATUS_PENDING]);
        $this->aggregator->recalculateTotals($invoice);
        return $this->ok(new InvoiceResource($invoice->fresh()), 'Invoice finalised');
    }

    public function applyDiscount(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount'  => ['nullable', 'numeric', 'min:0'],
            'amount'           => ['nullable', 'numeric', 'min:0'], // alias
            'reason'           => ['required', 'string', 'max:500'],
        ]);

        $amount = (float) (
            $data['discount_amount']
            ?? $data['amount']
            ?? ($invoice->subtotal * (($data['discount_percent'] ?? 0) / 100))
        );

        if ($amount <= 0) {
            return $this->fail('Discount amount must be greater than zero.', 422);
        }

        $pct = $invoice->total_amount > 0
            ? ($amount / (float) $invoice->total_amount) * 100
            : 0;
        $threshold = (float) config('hms.billing.discount_approval_threshold_percent', 20);

        if ($pct > $threshold && ! auth()->user()?->hasAnyRole(['hospital_admin', 'super_admin'])) {
            return $this->fail("Discounts above {$threshold}% require administrator approval.", 403);
        }

        $invoice->update([
            'discount_amount' => $amount,
            'notes' => trim(($invoice->notes ?? '') . "\nDiscount: {$data['reason']}"),
        ]);

        return $this->ok(
            new InvoiceResource($this->aggregator->recalculateTotals($invoice)->load(['items', 'payments', 'patient'])),
            'Discount applied',
        );
    }
}
