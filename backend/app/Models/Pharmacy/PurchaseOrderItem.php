<?php
namespace App\Models\Pharmacy;

use App\Models\Clinical\Medication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'medication_id',
        'quantity', 'received_quantity',
        'unit_price', 'tax_percent', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'  => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'line_total'  => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function medication(): BelongsTo    { return $this->belongsTo(Medication::class); }
}
