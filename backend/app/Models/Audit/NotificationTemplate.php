<?php
namespace App\Models\Audit;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['facility_id', 'code', 'name', 'channel', 'subject', 'body', 'language', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }

    public function render(array $vars): array
    {
        $apply = function ($text) use ($vars) {
            foreach ($vars as $k => $v) {
                $text = str_replace('{{' . $k . '}}', (string) $v, $text);
            }
            return $text;
        };
        return ['subject' => $apply($this->subject ?? ''), 'body' => $apply($this->body)];
    }
}
