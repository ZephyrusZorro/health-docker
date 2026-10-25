<?php
namespace App\Models\Billing;

use App\Models\Identity\Facility;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'refund_number', 'facility_id', 'payment_id', 'invoice_id',
        'requested_by', 'approved_by', 'amount', 'reason',
        'status', 'processed_at', 'reference_number',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function payment(): BelongsTo  { return $this->belongsTo(Payment::class); }
    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
}
