<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FHIR Observation — vitals, lab values, exam findings, scores.
 * Unified model with value_type discriminator.
 *
 * Vitals are just Observations with category='vital-signs' and well-known codes:
 *   vital.bp_systolic / vital.bp_diastolic / vital.pulse / vital.temperature /
 *   vital.spo2 / vital.respiratory_rate / vital.weight / vital.height / vital.bmi
 */
class Observation extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'encounter_id', 'patient_id', 'recorded_by',
        'code', 'code_system', 'display_name', 'category', 'value_type',
        'value_numeric', 'value_string', 'value_boolean', 'unit',
        'reference_low', 'reference_high', 'interpretation',
        'effective_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_at'   => 'datetime',
            'value_numeric'  => 'decimal:4',
            'value_boolean'  => 'boolean',
            'reference_low'  => 'decimal:4',
            'reference_high' => 'decimal:4',
        ];
    }

    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function scopeVitals($q)     { return $q->where('category', 'vital-signs'); }
    public function scopeLab($q)        { return $q->where('category', 'laboratory'); }
    public function scopeAbnormal($q)   { return $q->whereIn('interpretation', ['low', 'high', 'critical_low', 'critical_high', 'abnormal']); }
}
