<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\Provider;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** FHIR MedicationRequest — the written prescription. */
class Prescription extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'prescription_number', 'encounter_id', 'patient_id', 'provider_id',
        'status', 'notes', 'prescribed_at', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_at' => 'datetime',
            'valid_until'   => 'date',
        ];
    }

    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function provider(): BelongsTo  { return $this->belongsTo(Provider::class); }
    public function items(): HasMany       { return $this->hasMany(PrescriptionItem::class); }

    public function scopeActive($q) { return $q->whereIn('status', ['active', 'partially_dispensed']); }
}
