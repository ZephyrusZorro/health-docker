<?php
namespace App\Models\Admission;

use App\Models\Identity\Facility;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bed extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_OCCUPIED    = 'occupied';
    public const STATUS_RESERVED    = 'reserved';
    public const STATUS_CLEANING    = 'cleaning';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_BLOCKED     = 'blocked';

    protected $fillable = [
        'facility_id', 'ward_id', 'room_id', 'bed_number',
        'status', 'daily_rate_override', 'notes',
    ];

    protected function casts(): array { return ['daily_rate_override' => 'decimal:2']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function ward(): BelongsTo     { return $this->belongsTo(Ward::class); }
    public function room(): BelongsTo     { return $this->belongsTo(Room::class); }

    public function statusHistory(): HasMany { return $this->hasMany(BedStatusHistory::class); }
    public function assignments(): HasMany   { return $this->hasMany(BedAssignment::class); }

    public function currentRate(): float
    {
        return (float) ($this->daily_rate_override ?? $this->room?->daily_rate ?? 0);
    }

    public function scopeAvailable($q) { return $q->where('status', self::STATUS_AVAILABLE); }
}
