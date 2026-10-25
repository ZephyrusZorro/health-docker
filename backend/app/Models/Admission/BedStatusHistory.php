<?php
namespace App\Models\Admission;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'bed_status_history';

    protected $fillable = [
        'bed_id', 'patient_id', 'admission_id', 'changed_by',
        'status', 'previous_status', 'start_at', 'end_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at'   => 'datetime',
        ];
    }

    public function bed(): BelongsTo       { return $this->belongsTo(Bed::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
