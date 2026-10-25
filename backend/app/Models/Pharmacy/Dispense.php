<?php
namespace App\Models\Pharmacy;

use App\Models\Clinical\Prescription;
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
 * A Dispense is the execution of a prescription (or an OTC POS sale).
 * Each DispenseItem decrements a specific MedicineBatch.
 */
class Dispense extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_COMPLETED        = 'completed';
    public const STATUS_CANCELLED        = 'cancelled';
    public const STATUS_PARTIAL_RETURN   = 'partially_returned';
    public const STATUS_RETURNED         = 'returned';

    protected $fillable = [
        'dispense_number', 'facility_id', 'prescription_id',
        'patient_id', 'pharmacist_id', 'dispense_type', 'status',
        'subtotal', 'tax_amount', 'discount_amount', 'total_amount',
        'dispensed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount'    => 'decimal:2',
            'dispensed_at'    => 'datetime',
        ];
    }

    public function facility(): BelongsTo     { return $this->belongsTo(Facility::class); }
    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function patient(): BelongsTo      { return $this->belongsTo(Patient::class); }
    public function pharmacist(): BelongsTo   { return $this->belongsTo(User::class, 'pharmacist_id'); }
    public function items(): HasMany          { return $this->hasMany(DispenseItem::class); }
    public function returns(): HasMany        { return $this->hasMany(DispenseReturn::class); }
}
