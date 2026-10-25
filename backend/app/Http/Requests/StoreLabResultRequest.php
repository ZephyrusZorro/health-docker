<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Single result entry for one lab_order_test row. The controller derives
 * is_abnormal automatically from the test's reference range.
 */
class StoreLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && (
            $u->can('lab.result')
            || $u->can('lab_result.enter')
            || $u->hasAnyRole(['lab_technician', 'lab_tech', 'hospital_admin', 'super_admin'])
        );
    }

    public function rules(): array
    {
        return [
            'value_numeric'  => ['nullable', 'numeric'],
            'value_text'     => ['nullable', 'string', 'max:500'],
            'value_string'   => ['nullable', 'string', 'max:500'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'abnormal_flag'  => ['nullable', 'string', 'max:30'],
            'interpretation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Require at least one of value_numeric or value_text/value_string. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('value_numeric') === null
                && ! $this->filled('value_text')
                && ! $this->filled('value_string')) {
                $validator->errors()->add('value', 'A numeric or text value is required.');
            }
        });
    }
}
