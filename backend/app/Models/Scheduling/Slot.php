<?php
namespace App\Models\Scheduling;

use App\Models\Identity\Provider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Materialised time-slot. Overbooking prevented in service transaction. */
class Slot extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_template_id', 'provider_id', 'slot_date',
        'start_time', 'end_time', 'capacity', 'booked_count', 'status',
    ];

    protected function casts(): array { return ['slot_date' => 'date']; }

    public function template(): BelongsTo   { return $this->belongsTo(ScheduleTemplate::class, 'schedule_template_id'); }
    public function provider(): BelongsTo   { return $this->belongsTo(Provider::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }

    public function isFull(): bool { return $this->booked_count >= $this->capacity; }
}
