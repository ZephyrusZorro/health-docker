<?php
namespace App\Models\Scheduling;

use App\Models\Identity\Provider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Daily queue per provider — running token counter + currently serving. */
class Queue extends Model
{
    use HasFactory;
    protected $fillable = ['provider_id', 'queue_date', 'current_token', 'next_token', 'total_waiting', 'total_seen'];
    protected function casts(): array { return ['queue_date' => 'date']; }

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
