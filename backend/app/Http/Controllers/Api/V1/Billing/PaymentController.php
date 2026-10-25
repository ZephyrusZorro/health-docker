<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Events\PaymentReceived;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Billing\Invoice;
use App\Models\Billing\Payment;
use App\Services\Billing\InvoiceAggregator;
use Illuminate\Support\Facades\DB;

class PaymentController extends ApiController
{
    public function __construct(private readonly InvoiceAggregator $aggregator)
    {
    }

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        /** @var Invoice $invoice */
        $invoice = Invoice::findOrFail($data['invoice_id']);

        // Refresh balance first to be safe.
        $this->aggregator->recalculateTotals($invoice);
        $invoice->refresh();

        if ((float) $data['amount'] > (float) $invoice->balance_amount + 0.01) {
            return $this->fail('Payment exceeds outstanding balance.', 422);
        }

        $payment = DB::transaction(function () use ($data, $invoice) {
            $userId = auth()->id() ?? \App\Models\Identity\User::value('id');

            $p = Payment::create([
                'payment_number'   => $this->nextPaymentNumber($invoice->facility_id),
                'facility_id'      => $invoice->facility_id,
                'invoice_id'       => $invoice->id,
                'patient_id'       => $invoice->patient_id,
                'received_by'      => $userId,
                'payment_method'   => $data['payment_method'] ?? 'cash',
                'amount'           => $data['amount'],
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at'          => now(),
                'status'           => 'completed',
                'notes'            => $data['notes'] ?? null,
            ]);

            $this->aggregator->recalculateTotals($invoice);
            return $p;
        });

        event(new PaymentReceived($payment));

        return $this->created($payment, 'Payment recorded');
    }

    private function nextPaymentNumber(int $facilityId): string
    {
        $year  = now()->year;
        $month = now()->format('m');
        $count = Payment::where('facility_id', $facilityId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', (int) $month)
            ->count();
        $seq = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
        return sprintf('PAY-%d%s-%s', $year, $month, $seq);
    }
}
