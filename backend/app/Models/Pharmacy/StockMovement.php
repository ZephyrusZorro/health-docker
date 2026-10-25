<?php
namespace App\Models\Pharmacy;

use App\Models\Clinical\Medication;
use App\Models\Identity\Facility;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Unified stock ledger. Every IN or OUT writes a row with balance_after
 * snapshot — this is the auditable trail that backs SRS §4.7 business rules.
 */
class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE         = 'purchase';
    public const TYPE_DISPENSE         = 'dispense';
    public const TYPE_RETURN_IN        = 'return_in';
    public const TYPE_RETURN_OUT       = 'return_out';
    public const TYPE_ADJUSTMENT_IN    = 'adjustment_in';
    public const TYPE_ADJUSTMENT_OUT   = 'adjustment_out';
    public const TYPE_EXPIRY_WRITEOFF  = 'expiry_writeoff';

    protected $fillable = [
        'facility_id', 'medicine_batch_id', 'medication_id',
        'performed_by', 'movement_type', 'quantity', 'balance_after',
        'source_type', 'source_id', 'reason',
    ];

    public function facility(): BelongsTo   { return $this->belongsTo(Facility::class); }
    public function batch(): BelongsTo      { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
    public function medication(): BelongsTo { return $this->belongsTo(Medication::class); }
    public function performer(): BelongsTo  { return $this->belongsTo(User::class, 'performed_by'); }
    public function source(): MorphTo       { return $this->morphTo(); }

    public static function inboundTypes(): array
    {
        return [self::TYPE_PURCHASE, self::TYPE_RETURN_IN, self::TYPE_ADJUSTMENT_IN];
    }

    public static function outboundTypes(): array
    {
        return [self::TYPE_DISPENSE, self::TYPE_RETURN_OUT, self::TYPE_ADJUSTMENT_OUT, self::TYPE_EXPIRY_WRITEOFF];
    }
}
