<?php
namespace App\Models\Audit;

use App\Models\Identity\Facility;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id', 'slug', 'title', 'meta_description', 'meta_keywords',
        'content', 'status', 'author_id', 'published_at',
    ];

    protected function casts(): array { return ['published_at' => 'datetime']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function author(): BelongsTo   { return $this->belongsTo(User::class, 'author_id'); }

    public function scopePublished($q) { return $q->where('status', 'published'); }
}
