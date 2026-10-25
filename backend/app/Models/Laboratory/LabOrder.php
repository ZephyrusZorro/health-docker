<?php
namespace App\Models\Laboratory;

use App\Models\Clinical\Encounter;
use App\Models\Clinical\Order;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lab Order — specialization of the generic clinical Order for lab workflow.
 * Status: ordered → sample_collection_pending → sample_collected → in_process
 *         → result_pending_review → completed
 */
class LabOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_ORDERED                   = 'ordered';
    public const STATUS_SAMPLE_COLLECTION_PENDING = 'sample_collection_pending';
    public const STATUS_SAMPLE_COLLECTED          = 'sample_collected';
    public const STATUS_IN_PROCESS                = 'in_process';
    public const STATUS_RESULT_PENDING_REVIEW     = 'result_pending_review';
    public const STATUS_COMPLETED                 = 'completed';
    public const STATUS_CANCELLED                 = 'cancelled';

    protected $fillable = [
        'lab_order_number', 'order_id', 'facility_id', 'patient_id',
        'encounter_id', 'ordered_by', 'priority', 'status',
        'clinical_notes', 'ordered_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo     { return $this->belongsTo(Order::class); }
    public function facility(): BelongsTo  { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(User::class, 'ordered_by'); }

    public function tests(): HasMany       { return $this->hasMany(LabOrderTest::class); }
    public function samples(): HasMany     { return $this->hasMany(LabSample::class); }
    public function results(): HasMany     { return $this->hasMany(LabResult::class); }
    public function files(): HasMany       { return $this->hasMany(LabResultFile::class); }
}
