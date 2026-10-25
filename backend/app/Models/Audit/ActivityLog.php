<?php
namespace App\Models\Audit;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'description', 'log_name', 'properties'];
    protected function casts(): array { return ['properties' => 'array']; }

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function subject(): MorphTo  { return $this->morphTo(); }
}
