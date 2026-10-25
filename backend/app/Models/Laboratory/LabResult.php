<?php
namespace App\Models\Laboratory;

use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabResult extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'lab_order_id', 'lab_test_id', 'lab_order_test_id', 'lab_sample_id',
        'entered_by', 'parameter_name',
        'value_string', 'value_numeric', 'unit',
        'reference_low', 'reference_high', 'reference_text',
        'interpretation', 'is_abnormal', 'status',
        'technician_notes', 'result_date', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric'  => 'decimal:4',
            'reference_low'  => 'decimal:4',
            'reference_high' => 'decimal:4',
            'is_abnormal'    => 'boolean',
            'result_date'    => 'datetime',
            'verified_at'    => 'datetime',
        ];
    }

    public function labOrder(): BelongsTo      { return $this->belongsTo(LabOrder::class); }
    public function labTest(): BelongsTo       { return $this->belongsTo(LabTest::class); }
    public function labOrderTest(): BelongsTo  { return $this->belongsTo(LabOrderTest::class); }
    public function sample(): BelongsTo        { return $this->belongsTo(LabSample::class, 'lab_sample_id'); }
    public function enteredBy(): BelongsTo     { return $this->belongsTo(User::class, 'entered_by'); }
    public function verifier(): BelongsTo      { return $this->belongsTo(User::class, 'verified_by'); }

    /**
     * Derive interpretation and is_abnormal from numeric value + reference range.
     * Called automatically when saving.
     */
    public function autoInterpret(): void
    {
        if ($this->value_numeric === null || $this->reference_low === null || $this->reference_high === null) {
            return;
        }

        $val  = (float) $this->value_numeric;
        $low  = (float) $this->reference_low;
        $high = (float) $this->reference_high;

        if ($val < $low)       { $this->interpretation = 'low'; $this->is_abnormal = true; }
        elseif ($val > $high)  { $this->interpretation = 'high'; $this->is_abnormal = true; }
        else                   { $this->interpretation = 'normal'; $this->is_abnormal = false; }
    }
}
