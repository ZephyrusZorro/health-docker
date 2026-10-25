<?php

namespace App\Models\Identity;

use App\Models\Admission\Admission;
use App\Models\Billing\Invoice;
use App\Models\Clinical\Encounter;
use App\Models\Scheduling\Appointment;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Patient — the root of all clinical data. UHID is the immutable handle.
 *
 * Business rules (SRS §4.1):
 *  - One patient = one master identity. Duplicates are merged, not created.
 *  - UHID is system-generated and immutable.
 *  - Demographic edits must be audit-logged.
 */
class Patient extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'facility_id', 'user_id', 'uhid',
        'first_name', 'last_name', 'preferred_name',
        'gender', 'date_of_birth', 'is_dob_estimated',
        'blood_group', 'marital_status', 'occupation',
        'phone', 'phone_alt', 'email',
        'address', 'emergency_contact',
        'payer_type', 'insurance_provider', 'insurance_number',
        'referred_by', 'national_id', 'notes',
        'is_vip', 'is_deceased', 'deceased_date',
        'merged_into_patient_id', 'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'     => 'date',
            'deceased_date'     => 'date',
            'registered_at'     => 'datetime',
            'address'           => 'array',
            'emergency_contact' => 'array',
            'national_id'       => 'array',
            'is_dob_estimated'  => 'boolean',
            'is_vip'            => 'boolean',
            'is_deceased'       => 'boolean',
        ];
    }

    /**
     * The UHID is immutable (SRS §4.1). Strip it from any mass-update input.
     */
    protected function auditIgnoredAttributes(): array
    {
        return array_merge(parent::auditIgnoredAttributes() ?? [], []);
    }

    // ---- Relations ----------------------------------------------------------

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_patient_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContact::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ---- Accessors & helpers -----------------------------------------------

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->diffInYears(now());
    }

    public function scopeActive($q)
    {
        return $q->whereNull('merged_into_patient_id')
                 ->where('is_deceased', false);
    }

    public function scopeSearch($q, string $term)
    {
        $term = trim($term);
        if ($term === '') return $q;

        return $q->where(function ($q) use ($term) {
            $q->where('uhid',  'ILIKE', "%{$term}%")
              ->orWhere('first_name', 'ILIKE', "%{$term}%")
              ->orWhere('last_name',  'ILIKE', "%{$term}%")
              ->orWhere('phone',      'ILIKE', "%{$term}%")
              ->orWhere('email',      'ILIKE', "%{$term}%");
        });
    }
}
