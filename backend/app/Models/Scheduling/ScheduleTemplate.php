<?php
namespace App\Models\Scheduling;

use App\Models\Identity\Provider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Weekly recurring schedule pattern for a provider. SlotGenerator materialises
 * concrete time-slots from these templates on demand.
 */
class ScheduleTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_id', 'facility_id', 'name', 'day_of_week', 'start_time', 'end_time',
        'slot_minutes', 'buffer_minutes', 'max_patients_per_slot', 'effective_from',
        'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function slots(): HasMany      { return $this->hasMany(Slot::class); }
}
