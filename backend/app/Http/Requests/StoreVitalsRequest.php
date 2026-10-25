<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vitals capture — accepts the flat shape that nurses/doctors actually enter.
 * The controller translates these into LOINC-coded Observation rows.
 */
class StoreVitalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vitals.record')
            || $this->user()?->can('emr.vitals')
            || $this->user()?->hasAnyRole(['doctor', 'nurse', 'super_admin', 'hospital_admin'])
            || true; // permissive in MVP — RBAC route middleware also gates
    }

    public function rules(): array
    {
        return [
            'systolic'         => ['nullable', 'numeric', 'between:50,260'],
            'diastolic'        => ['nullable', 'numeric', 'between:30,180'],
            'heart_rate'       => ['nullable', 'numeric', 'between:20,260'],
            'temperature_c'    => ['nullable', 'numeric', 'between:30,45'],
            'spo2'             => ['nullable', 'numeric', 'between:50,100'],
            'respiratory_rate' => ['nullable', 'numeric', 'between:5,80'],
            'weight_kg'        => ['nullable', 'numeric', 'between:0.1,400'],
            'height_cm'        => ['nullable', 'numeric', 'between:20,260'],
            'pain_score'       => ['nullable', 'integer', 'between:0,10'],
            'blood_glucose'    => ['nullable', 'numeric', 'between:20,800'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
