<?php
namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispenseReturnItem extends Model
{
    use HasFactory;

    protected $fillable = ['dispense_return_id', 'dispense_item_id', 'quantity', 'refund_amount'];

    protected function casts(): array { return ['refund_amount' => 'decimal:2']; }

    public function dispenseReturn(): BelongsTo { return $this->belongsTo(DispenseReturn::class); }
    public function dispenseItem(): BelongsTo   { return $this->belongsTo(DispenseItem::class); }
}
