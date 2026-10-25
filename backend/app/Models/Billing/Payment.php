<?php
namespace App\Models\Billing;

use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'payment_number', 'facility_id', 'invoice_id', 'patient_id',
        'received_by', 'payment_method', 'amount', 'reference_number',
        'gateway', 'gateway_response', 'paid_at', 'status', 'notes',
        'cashier_shift_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'paid_at'          => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function patient(): BelongsTo  { return $this->belongsTo(Patient::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function shift(): BelongsTo    { return $this->belongsTo(CashierShift::class, 'cashier_shift_id'); }
}
