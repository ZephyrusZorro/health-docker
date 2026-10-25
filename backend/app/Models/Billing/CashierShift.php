<?php
namespace App\Models\Billing;

use App\Models\Identity\Facility;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id', 'cashier_id', 'opened_at', 'closed_at',
        'opening_balance', 'expected_closing_balance', 'actual_closing_balance',
        'variance', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'                => 'datetime',
            'closed_at'                => 'datetime',
            'opening_balance'          => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'actual_closing_balance'   => 'decimal:2',
            'variance'                 => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function cashier(): BelongsTo  { return $this->belongsTo(User::class, 'cashier_id'); }
    public function payments(): HasMany   { return $this->hasMany(Payment::class); }
}
