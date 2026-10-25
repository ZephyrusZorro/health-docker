<?php
namespace App\Models\Audit;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id', 'notifiable_type', 'notifiable_id',
        'template_code', 'channel', 'recipient', 'subject', 'body',
        'status', 'provider', 'provider_message_id', 'error_message',
        'sent_at', 'delivered_at', 'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'      => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo   { return $this->belongsTo(Facility::class); }
    public function notifiable(): MorphTo   { return $this->morphTo(); }
}
