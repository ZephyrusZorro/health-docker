<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'invoice_number'  => $this->invoice_number,
            'invoice_type'    => $this->invoice_type,
            'status'          => $this->status,

            // Canonical (matches DB columns)
            'invoice_date'    => $this->invoice_date?->toDateString(),
            'due_date'        => $this->due_date?->toDateString(),
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount'      => (float) $this->tax_amount,
            'total_amount'    => (float) $this->total_amount,
            'paid_amount'     => (float) $this->paid_amount,
            'refunded_amount' => (float) $this->refunded_amount,
            'balance_amount'  => (float) $this->balance_amount,
            'currency'        => $this->currency,

            // Frontend-friendly aliases (keep TypeScript types compatible)
            'issue_date'      => $this->invoice_date?->toDateString(),
            'amount_paid'     => (float) $this->paid_amount,
            'amount_due'      => (float) $this->balance_amount,

            'patient_id'      => $this->patient_id,
            'encounter_id'    => $this->encounter_id,
            'admission_id'    => $this->admission_id,

            'patient' => $this->whenLoaded('patient', fn () => [
                'id'         => $this->patient->id,
                'uhid'       => $this->patient->uhid,
                'first_name' => $this->patient->first_name,
                'last_name'  => $this->patient->last_name,
                'full_name'  => trim("{$this->patient->first_name} {$this->patient->last_name}"),
            ]),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'              => $i->id,
                'invoice_id'      => $i->invoice_id,
                'category'        => $i->category,
                'item_name'       => $i->item_name,
                'description'     => $i->item_name, // alias
                'item_code'       => $i->item_code,
                'quantity'        => (int) $i->quantity,
                'unit_price'      => (float) $i->unit_price,
                'discount_amount' => (float) $i->discount_amount,
                'tax_percent'     => (float) $i->tax_percent,
                'tax_amount'      => (float) $i->tax_amount,
                'line_total'      => (float) $i->line_total,
                'source_type'     => $i->source_type,
                'source_id'       => $i->source_id,
            ])),

            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id'                => $p->id,
                'invoice_id'        => $p->invoice_id,
                'patient_id'        => $p->patient_id,
                'payment_number'    => $p->payment_number,
                'receipt_number'    => $p->payment_number, // alias
                'amount'            => (float) $p->amount,
                'method'            => $p->payment_method,
                'payment_method'    => $p->payment_method,
                'reference_number'  => $p->reference_number,
                'status'            => $p->status,
                'paid_at'           => $p->paid_at?->toIso8601String(),
                'received_at'       => $p->paid_at?->toIso8601String(), // alias
            ])),
        ];
    }
}
