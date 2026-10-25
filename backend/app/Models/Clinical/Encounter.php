<?php
namespace App\Models\Clinical;

use App\Models\Identity\Department;
use App\Models\Identity\Patient;
use App\Models\Identity\Provider;
use App\Models\Scheduling\Appointment;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Encounter = clinical interaction with a patient (OPD visit, IPD stay,
 * emergency, telemedicine). Every note, observation, order and prescription
 * hangs off a single Encounter for auditability (SRS §4.6).
 */
class Encounter extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'encounter_number', 'facility_id', 'patient_id', 'provider_id',
        'appointment_id', 'department_id', 'created_by',
        'encounter_type', 'status',
        'started_at', 'ended_at', 'chief_complaint',
        'history_of_present_illness', 'examination_findings',
        'assessment', 'plan', 'advice_to_patient', 'clinical_summary',
        'follow_up_date', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at'     => 'datetime',
            'ended_at'       => 'datetime',
            'follow_up_date' => 'date',
            'metadata'       => 'array',
        ];
    }

    public function patient(): BelongsTo     { return $this->belongsTo(Patient::class); }
    public function provider(): BelongsTo    { return $this->belongsTo(Provider::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function department(): BelongsTo  { return $this->belongsTo(Department::class); }

    public function observations(): HasMany  { return $this->hasMany(Observation::class); }
    public function conditions(): HasMany    { return $this->hasMany(Condition::class); }
    public function prescriptions(): HasMany { return $this->hasMany(Prescription::class); }
    public function orders(): HasMany        { return $this->hasMany(Order::class); }
    public function notes(): HasMany         { return $this->hasMany(ClinicalNote::class); }
    public function attachments(): HasMany   { return $this->hasMany(ClinicalAttachment::class); }
}
