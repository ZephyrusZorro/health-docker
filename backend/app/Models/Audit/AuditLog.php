<?php
namespace App\Models\Audit;

use App\Models\Identity\Facility;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AuditLog — immutable log of every sensitive action. No soft-deletes:
 * audit trail is legally append-only (SRS §12.3).
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;                // created_at only

    protected $fillable = [
        'facility_id', 'user_id', 'user_email', 'user_role',
        'event', 'category', 'severity',
        'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'context',
        'ip_address', 'user_agent', 'request_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function auditable(): MorphTo  { return $this->morphTo(); }
}
