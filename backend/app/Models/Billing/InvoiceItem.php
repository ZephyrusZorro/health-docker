<?php
namespace App\Models\Billing;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'source_type', 'source_id',
        'item_code', 'item_name', 'category', 'quantity',
        'unit_price', 'discount_percent', 'discount_amount',
        'tax_percent', 'tax_amount', 'line_total',
        'discount_approved_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'       => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'tax_percent'      => 'decimal:2',
            'tax_amount'       => 'decimal:2',
            'line_total'       => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo         { return $this->belongsTo(Invoice::class); }
    public function source(): MorphTo            { return $this->morphTo(); }
    public function discountApprover(): BelongsTo { return $this->belongsTo(User::class, 'discount_approved_by'); }
}
