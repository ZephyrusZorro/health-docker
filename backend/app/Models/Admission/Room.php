<?php
namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['ward_id', 'code', 'name', 'capacity', 'daily_rate', 'amenities', 'is_active'];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'amenities'  => 'array',
            'is_active'  => 'boolean',
        ];
    }

    public function ward(): BelongsTo { return $this->belongsTo(Ward::class); }
    public function beds(): HasMany   { return $this->hasMany(Bed::class); }
}
