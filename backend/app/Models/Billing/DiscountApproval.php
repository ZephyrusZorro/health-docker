<?php
namespace App\Models\Billing;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_type', 'target_id', 'requested_by', 'approved_by',
        'discount_percent', 'discount_amount', 'reason',
        'status', 'decided_at', 'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'decided_at'       => 'datetime',
        ];
    }

    public function target(): MorphTo      { return $this->morphTo(); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
}
