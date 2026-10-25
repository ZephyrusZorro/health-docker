<?php
namespace App\Models\Scheduling;

use App\Models\Clinical\Encounter;
use App\Models\Identity\Patient;
use App\Models\Identity\Provider;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Appointment = reservation of a Slot by a Patient with a Provider.
 * Lifecycle (SRS §4.2): booked → checked_in → in_consultation → completed
 *                                         ↘ cancelled / no_show
 */
class Appointment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_BOOKED      = 'booked';
    public const STATUS_CONFIRMED   = 'confirmed';
    public const STATUS_CHECKED_IN  = 'checked_in';
    public const STATUS_IN_CONSULT  = 'in_consultation';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_NO_SHOW     = 'no_show';
    public const STATUS_RESCHEDULED = 'rescheduled';

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_BOOKED     => [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_RESCHEDULED, self::STATUS_NO_SHOW],
        self::STATUS_CONFIRMED  => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_RESCHEDULED, self::STATUS_NO_SHOW],
        self::STATUS_CHECKED_IN => [self::STATUS_IN_CONSULT, self::STATUS_CANCELLED],
        self::STATUS_IN_CONSULT => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED  => [], self::STATUS_CANCELLED => [],
        self::STATUS_NO_SHOW    => [], self::STATUS_RESCHEDULED => [],
    ];

    protected $fillable = [
        'appointment_number', 'patient_id', 'provider_id', 'slot_id',
        'visit_type_id', 'facility_id',
        'scheduled_start', 'scheduled_end', 'status', 'token_number',
        'booking_source', 'advance_fee_paid', 'fee_amount',
        'chief_complaint', 'notes', 'cancellation_reason',
        'checked_in_at', 'consult_started_at', 'consult_ended_at',
        'created_by', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start'    => 'datetime',
            'scheduled_end'      => 'datetime',
            'checked_in_at'      => 'datetime',
            'consult_started_at' => 'datetime',
            'consult_ended_at'   => 'datetime',
            'advance_fee_paid'   => 'boolean',
            'fee_amount'         => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function provider(): BelongsTo  { return $this->belongsTo(Provider::class); }
    public function slot(): BelongsTo      { return $this->belongsTo(Slot::class); }
    public function visitType(): BelongsTo { return $this->belongsTo(VisitType::class); }
    public function encounter(): HasOne    { return $this->hasOne(Encounter::class); }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    public function scopeToday($q)  { return $q->whereBetween('scheduled_start', [now()->startOfDay(), now()->endOfDay()]); }
    public function scopeActive($q) { return $q->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_NO_SHOW, self::STATUS_COMPLETED]); }
}
