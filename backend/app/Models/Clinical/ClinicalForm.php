<?php
namespace App\Models\Clinical;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['facility_id', 'code', 'name', 'description', 'category', 'schema', 'is_active'];
    protected function casts(): array { return ['schema' => 'array', 'is_active' => 'boolean']; }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
}
