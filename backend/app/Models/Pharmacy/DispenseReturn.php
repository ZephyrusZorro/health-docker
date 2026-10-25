<?php
namespace App\Models\Pharmacy;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispenseReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number', 'dispense_id', 'processed_by',
        'reason', 'refund_amount', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'returned_at'   => 'datetime',
        ];
    }

    public function dispense(): BelongsTo   { return $this->belongsTo(Dispense::class); }
    public function processor(): BelongsTo  { return $this->belongsTo(User::class, 'processed_by'); }
    public function items(): HasMany        { return $this->hasMany(DispenseReturnItem::class); }
}
