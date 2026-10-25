<?php
namespace App\Models\Laboratory;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabSample extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_number', 'lab_order_id', 'patient_id', 'collected_by',
        'specimen_type', 'container_type', 'status',
        'collected_at', 'received_at', 'rejection_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'received_at'  => 'datetime',
        ];
    }

    public function labOrder(): BelongsTo  { return $this->belongsTo(LabOrder::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function collector(): BelongsTo { return $this->belongsTo(User::class, 'collected_by'); }
}
