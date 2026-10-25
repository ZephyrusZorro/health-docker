<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** FHIR Condition — diagnoses (ICD-10 ready). */
class Condition extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'patient_id', 'encounter_id', 'recorded_by',
        'code', 'code_system', 'display_name',
        'clinical_status', 'verification_status', 'severity', 'category',
        'onset_date', 'abatement_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'onset_date'     => 'date',
            'abatement_date' => 'date',
        ];
    }

    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function scopeActive($q) { return $q->whereIn('clinical_status', ['active', 'recurrence', 'relapse']); }
}
