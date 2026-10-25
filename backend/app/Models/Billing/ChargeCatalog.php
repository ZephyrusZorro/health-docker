<?php
namespace App\Models\Billing;

use App\Models\Identity\Department;
use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeCatalog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'charge_catalog';

    protected $fillable = [
        'facility_id', 'code', 'name', 'category', 'department_id',
        'unit_price', 'tax_percent', 'hsn_code', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'  => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'is_active'   => 'boolean',
        ];
    }

    public function facility(): BelongsTo   { return $this->belongsTo(Facility::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }

    public function packageItems(): HasMany { return $this->hasMany(PackageItem::class, 'package_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
