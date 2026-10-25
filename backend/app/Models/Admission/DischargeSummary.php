<?php
namespace App\Models\Admission;

use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DischargeSummary extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'admission_id', 'prepared_by',
        'presenting_complaints', 'history', 'examination_findings',
        'investigations_summary', 'treatment_given', 'course_in_hospital',
        'condition_at_discharge', 'discharge_diagnosis', 'discharge_medications',
        'follow_up_advice', 'follow_up_date', 'diet_advice', 'activity_restrictions',
        'is_finalized', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'is_finalized'   => 'boolean',
            'finalized_at'   => 'datetime',
            'follow_up_date' => 'date',
        ];
    }

    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function preparer(): BelongsTo  { return $this->belongsTo(User::class, 'prepared_by'); }
}
