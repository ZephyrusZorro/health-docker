<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** FHIR AllergyIntolerance. */
class Allergy extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'patient_id', 'recorded_by', 'type', 'category', 'criticality',
        'substance', 'substance_code', 'reaction', 'severity', 'status',
        'onset_date', 'notes',
    ];

    protected function casts(): array { return ['onset_date' => 'date']; }

    public function patient(): BelongsTo    { return $this->belongsTo(Patient::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
