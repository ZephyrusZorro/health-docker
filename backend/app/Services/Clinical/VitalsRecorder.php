<?php

namespace App\Services\Clinical;

use App\Models\Clinical\Encounter;
use App\Models\Clinical\Observation;
use Illuminate\Support\Facades\Auth;

/**
 * VitalsRecorder — translates a flat vitals payload (the shape that nurses
 * actually fill in) into a set of FHIR Observation rows with LOINC codes.
 *
 * Reference codes: LOINC catalog (Logical Observation Identifiers Names and
 * Codes). Using LOINC keeps us interoperable with FHIR, HL7 v2, and major
 * EMR vendors (SRS §5.6 Compliance & Interoperability).
 */
class VitalsRecorder
{
    /**
     * Mapping from form-field → [LOINC code, display, unit, ref_low, ref_high].
     * Reference ranges are adult-normal; not appropriate for paediatrics.
     */
    private const VITALS = [
        'systolic'         => ['8480-6',  'Systolic blood pressure',           'mmHg',       90,  140],
        'diastolic'        => ['8462-4',  'Diastolic blood pressure',          'mmHg',       60,   90],
        'heart_rate'       => ['8867-4',  'Heart rate',                        'bpm',        60,  100],
        'temperature_c'    => ['8310-5',  'Body temperature',                  'Cel',      36.1, 37.2],
        'spo2'             => ['59408-5', 'Oxygen saturation (SpO2)',          '%',          95,  100],
        'respiratory_rate' => ['9279-1',  'Respiratory rate',                  'breaths/min',12,   20],
        'weight_kg'        => ['29463-7', 'Body weight',                       'kg',         null, null],
        'height_cm'        => ['8302-2',  'Body height',                       'cm',         null, null],
        'pain_score'       => ['72514-3', 'Pain severity (0-10 numeric scale)','{score}',    0,    3],
        'blood_glucose'    => ['2339-0',  'Glucose (Mass/volume) in blood',    'mg/dL',      70,  140],
    ];

    /**
     * @return array<int, Observation>
     */
    public function record(Encounter $encounter, array $payload): array
    {
        $userId = Auth::id() ?? $encounter->created_by ?? \App\Models\Identity\User::value('id');
        $now    = now();
        $created = [];

        foreach (self::VITALS as $field => [$code, $display, $unit, $refLow, $refHigh]) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                continue;
            }
            $value = (float) $payload[$field];

            $created[] = Observation::create([
                'encounter_id'  => $encounter->id,
                'patient_id'    => $encounter->patient_id,
                'recorded_by'   => $userId,
                'code'          => $code,
                'code_system'   => 'loinc',
                'display_name'  => $display,
                'category'      => 'vital-signs',
                'value_type'    => 'numeric',
                'value_numeric' => $value,
                'unit'          => $unit,
                'reference_low' => $refLow,
                'reference_high'=> $refHigh,
                'interpretation'=> $this->interpret($value, $refLow, $refHigh),
                'effective_at'  => $now,
                'notes'         => $payload['notes'] ?? null,
            ]);
        }

        // BMI is computed if both weight and height are present.
        if (isset($payload['weight_kg']) && isset($payload['height_cm']) && $payload['height_cm'] > 0) {
            $h = ((float) $payload['height_cm']) / 100;
            $bmi = round(((float) $payload['weight_kg']) / ($h * $h), 1);
            $created[] = Observation::create([
                'encounter_id'  => $encounter->id,
                'patient_id'    => $encounter->patient_id,
                'recorded_by'   => $userId,
                'code'          => '39156-5',
                'code_system'   => 'loinc',
                'display_name'  => 'Body mass index (BMI)',
                'category'      => 'vital-signs',
                'value_type'    => 'numeric',
                'value_numeric' => $bmi,
                'unit'          => 'kg/m2',
                'reference_low' => 18.5,
                'reference_high'=> 24.9,
                'interpretation'=> $this->interpret($bmi, 18.5, 24.9),
                'effective_at'  => $now,
            ]);
        }

        return $created;
    }

    private function interpret(float $value, ?float $low, ?float $high): ?string
    {
        if ($low === null && $high === null) return null;
        if ($low !== null  && $value < $low)  return 'low';
        if ($high !== null && $value > $high) return 'high';
        return 'normal';
    }
}
