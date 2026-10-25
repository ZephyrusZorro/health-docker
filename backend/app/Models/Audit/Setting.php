<?php
namespace App\Models\Audit;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['facility_id', 'namespace', 'key', 'value', 'type', 'description'];

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
}
