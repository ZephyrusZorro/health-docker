<?php
namespace App\Models\Admission;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ward extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id', 'code', 'name', 'building', 'floor',
        'category', 'gender_restriction', 'total_beds', 'is_active',
    ];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function rooms(): HasMany      { return $this->hasMany(Room::class); }
    public function beds(): HasMany       { return $this->hasMany(Bed::class); }
}
