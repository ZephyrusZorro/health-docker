<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('billing.collect')
            || $u->can('payment.collect')
            || $u->hasAnyRole(['cashier', 'receptionist', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'invoice_id'        => ['required', 'integer', 'exists:invoices,id'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'method'            => ['nullable', 'in:cash,card,upi,netbanking,cheque,insurance,wallet,other'],
            'payment_method'    => ['nullable', 'in:cash,card,upi,netbanking,cheque,insurance,wallet,other'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'reference_number'  => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Accept either method/payment_method and reference/reference_number. */
    public function prepareForValidation(): void
    {
        if ($this->filled('method') && ! $this->filled('payment_method')) {
            $this->merge(['payment_method' => $this->input('method')]);
        }
        if ($this->filled('reference') && ! $this->filled('reference_number')) {
            $this->merge(['reference_number' => $this->input('reference')]);
        }
    }
}
