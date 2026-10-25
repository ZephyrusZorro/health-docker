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

class GoodsReceipt extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'grn_number', 'facility_id', 'purchase_order_id', 'supplier_id',
        'received_by', 'received_date', 'invoice_number', 'invoice_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date'  => 'date',
            'invoice_amount' => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo      { return $this->belongsTo(Facility::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier(): BelongsTo      { return $this->belongsTo(Supplier::class); }
    public function receiver(): BelongsTo      { return $this->belongsTo(User::class, 'received_by'); }
    public function items(): HasMany           { return $this->hasMany(GoodsReceiptItem::class); }
}
