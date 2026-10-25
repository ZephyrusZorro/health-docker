<?php
namespace App\Models\Audit;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;
    public $timestamps = false;

    protected $fillable = ['event_name', 'aggregate_type', 'aggregate_id', 'actor_id', 'payload', 'correlation_id', 'occurred_at'];
    protected function casts(): array { return ['payload' => 'array', 'occurred_at' => 'datetime']; }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
