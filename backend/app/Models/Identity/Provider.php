<?php

namespace App\Models\Identity;

use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\ScheduleTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Provider is a clinical user: doctor, nurse, lab tech, pharmacist.
 * They own schedules, write notes, and can place orders.
 */
class Provider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'facility_id', 'department_id', 'provider_type',
        'registration_number', 'qualification', 'specialization',
        'years_of_experience', 'consultation_fee', 'bio',
        'languages', 'is_available_online',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee'    => 'decimal:2',
            'languages'           => 'array',
            'is_available_online' => 'boolean',
        ];
    }

    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function facility(): BelongsTo    { return $this->belongsTo(Facility::class); }
    public function department(): BelongsTo  { return $this->belongsTo(Department::class); }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeDoctors($q)
    {
        return $q->where('provider_type', 'doctor');
    }

    public function scopeAvailableOnline($q)
    {
        return $q->where('is_available_online', true)
                 ->whereHas('user', fn ($u) => $u->where('is_active', true));
    }
}
