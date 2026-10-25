<?php
namespace App\Models\Pharmacy;

use App\Models\Identity\Facility;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'po_number', 'facility_id', 'supplier_id', 'created_by',
        'status', 'order_date', 'expected_delivery_date',
        'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date'             => 'date',
            'expected_delivery_date' => 'date',
            'subtotal'               => 'decimal:2',
            'tax_amount'             => 'decimal:2',
            'discount_amount'        => 'decimal:2',
            'total_amount'           => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany      { return $this->hasMany(PurchaseOrderItem::class); }
    public function receipts(): HasMany   { return $this->hasMany(GoodsReceipt::class); }
}
