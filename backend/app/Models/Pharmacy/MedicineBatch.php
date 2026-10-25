<?php
namespace App\Models\Pharmacy;

use App\Models\Clinical\Medication;
use App\Models\Identity\Facility;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A specific batch of a specific medication in stock at a facility.
 * This is where pharmacy inventory actually LIVES. FEFO ordering by expiry.
 */
class MedicineBatch extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'facility_id', 'medication_id', 'supplier_id',
        'batch_number', 'manufacture_date', 'expiry_date',
        'purchase_price', 'mrp', 'selling_price', 'tax_percent',
        'received_quantity', 'current_quantity', 'storage_location',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date'      => 'date',
            'purchase_price'   => 'decimal:2',
            'mrp'              => 'decimal:2',
            'selling_price'    => 'decimal:2',
            'tax_percent'      => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo   { return $this->belongsTo(Facility::class); }
    public function medication(): BelongsTo { return $this->belongsTo(Medication::class); }
    public function supplier(): BelongsTo   { return $this->belongsTo(Supplier::class); }
    public function movements(): HasMany    { return $this->hasMany(StockMovement::class, 'medicine_batch_id'); }

    // ---- Scopes ------------------------------------------------------------

    public function scopeNotExpired(Builder $q): Builder
    {
        return $q->where('expiry_date', '>=', now()->toDateString());
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where('current_quantity', '>', 0);
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeFefoOrder(Builder $q): Builder
    {
        return $q->orderBy('expiry_date')->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
