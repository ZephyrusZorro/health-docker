<?php
namespace App\Models\Billing;

use App\Models\Admission\Admission;
use App\Models\Clinical\Encounter;
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
 * Unified Invoice. Every InvoiceItem is source-linked to its originating
 * service (Encounter, BedAssignment, Dispense, LabOrder, etc.) — that is
 * the non-negotiable SRS §4.9 business rule this model enforces.
 */
class Invoice extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    protected $fillable = [
        'invoice_number', 'facility_id', 'patient_id', 'encounter_id',
        'admission_id', 'payer_type_id', 'created_by',
        'invoice_type', 'status', 'invoice_date', 'due_date',
        'subtotal', 'discount_amount', 'tax_amount',
        'total_amount', 'paid_amount', 'refunded_amount', 'balance_amount',
        'notes', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'    => 'date',
            'due_date'        => 'date',
            'subtotal'        => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'total_amount'    => 'decimal:2',
            'paid_amount'     => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'balance_amount'  => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo   { return $this->belongsTo(Facility::class); }
    public function patient(): BelongsTo    { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo  { return $this->belongsTo(Encounter::class); }
    public function admission(): BelongsTo  { return $this->belongsTo(Admission::class); }
    public function payerType(): BelongsTo  { return $this->belongsTo(PayerType::class); }
    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }

    public function items(): HasMany    { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function refunds(): HasMany  { return $this->hasMany(Refund::class); }

    public function recalculateBalance(): void
    {
        $this->balance_amount = (float) $this->total_amount
                              - (float) $this->paid_amount
                              + (float) $this->refunded_amount;

        if ($this->balance_amount <= 0.01) {
            $this->status = self::STATUS_PAID;
        } elseif ((float) $this->paid_amount > 0) {
            $this->status = self::STATUS_PARTIAL;
        }
    }
}
