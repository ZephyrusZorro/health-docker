<?php
namespace App\Models\Clinical;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id', 'medication_id', 'medication_text',
        'strength', 'dosage', 'frequency', 'route',
        'duration_days', 'quantity', 'timing_relation', 'instructions',
        'is_substitution_allowed', 'dispensed_quantity', 'status',
    ];

    protected function casts(): array
    {
        return ['is_substitution_allowed' => 'boolean'];
    }

    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function medication(): BelongsTo   { return $this->belongsTo(Medication::class); }

    public function remainingQuantity(): int
    {
        return max(0, ($this->quantity ?? 0) - ($this->dispensed_quantity ?? 0));
    }
}
