<?php
namespace App\Models\Admission;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id', 'bed_id', 'patient_id', 'assigned_by',
        'assigned_at', 'vacated_at', 'transfer_reason', 'daily_rate_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'         => 'datetime',
            'vacated_at'          => 'datetime',
            'daily_rate_snapshot' => 'decimal:2',
        ];
    }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function bed(): BelongsTo       { return $this->belongsTo(Bed::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function assigner(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_by'); }

    public function durationHours(): float
    {
        $end = $this->vacated_at ?? now();
        return $this->assigned_at?->diffInHours($end, true) ?? 0;
    }
}
