<?php
namespace App\Models\Laboratory;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id', 'code', 'name', 'category', 'test_type',
        'specimen_type', 'default_unit', 'reference_low', 'reference_high',
        'reference_text', 'price', 'turnaround_hours',
        'preparation_instructions', 'is_outsourced', 'outsource_partner', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reference_low'  => 'decimal:4',
            'reference_high' => 'decimal:4',
            'price'          => 'decimal:2',
            'is_outsourced'  => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }

    public function panelComponents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'lab_test_panel_items', 'panel_test_id', 'component_test_id')
                    ->withPivot('sort_order')->withTimestamps();
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
