<?php
namespace App\Models\Clinical;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Medication catalogue — shared by pharmacy batches and prescriptions. */
class Medication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'generic_name', 'brand_name', 'strength', 'form', 'category',
        'manufacturer', 'hsn_code', 'default_tax_percent',
        'is_prescription_required', 'is_controlled', 'reorder_level',
        'storage_instructions', 'common_side_effects', 'contraindications', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_percent'      => 'decimal:2',
            'is_prescription_required' => 'boolean',
            'is_controlled'            => 'boolean',
            'is_active'                => 'boolean',
            'contraindications'        => 'array',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(\App\Models\Pharmacy\MedicineBatch::class);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeSearch($q, string $term)
    {
        return $q->where(function ($q) use ($term) {
            $q->where('generic_name', 'ILIKE', "%{$term}%")
              ->orWhere('brand_name', 'ILIKE', "%{$term}%")
              ->orWhere('code', 'ILIKE', "%{$term}%");
        });
    }

    public function displayName(): string
    {
        return trim($this->brand_name ?: $this->generic_name) . ($this->strength ? " {$this->strength}" : '');
    }
}
