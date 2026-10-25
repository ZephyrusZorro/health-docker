<?php
namespace App\Models\Admission;

use App\Models\Identity\Department;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Identity\Provider;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Admission = one IPD stay. Can have multiple BedAssignments (transfers).
 * Discharge triggers billing closure workflow (SRS §4.4 Business Rules).
 */
class Admission extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_PENDING          = 'pending';
    public const STATUS_ACTIVE           = 'active';
    public const STATUS_DISCHARGED       = 'discharged';
    public const STATUS_ABSCONDED        = 'absconded';
    public const STATUS_TRANSFERRED_OUT  = 'transferred_out';
    public const STATUS_EXPIRED          = 'expired';
    public const STATUS_CANCELLED        = 'cancelled';

    protected $fillable = [
        'admission_number', 'facility_id', 'patient_id',
        'admitting_doctor_id', 'department_id', 'current_bed_id',
        'admission_type', 'status', 'admitted_at', 'discharged_at',
        'admission_reason', 'provisional_diagnosis',
        'admitted_by', 'discharged_by', 'discharge_type',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at'   => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo         { return $this->belongsTo(Patient::class); }
    public function facility(): BelongsTo        { return $this->belongsTo(Facility::class); }
    public function admittingDoctor(): BelongsTo { return $this->belongsTo(Provider::class, 'admitting_doctor_id'); }
    public function department(): BelongsTo      { return $this->belongsTo(Department::class); }
    public function currentBed(): BelongsTo      { return $this->belongsTo(Bed::class, 'current_bed_id'); }
    public function admittedBy(): BelongsTo      { return $this->belongsTo(User::class, 'admitted_by'); }
    public function dischargedBy(): BelongsTo    { return $this->belongsTo(User::class, 'discharged_by'); }

    public function bedAssignments(): HasMany    { return $this->hasMany(BedAssignment::class); }
    public function dischargeSummary(): HasOne   { return $this->hasOne(DischargeSummary::class); }

    public function scopeActive($q) { return $q->whereIn('status', ['pending', 'active']); }
}
