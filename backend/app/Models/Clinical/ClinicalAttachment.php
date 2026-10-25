<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ClinicalAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'encounter_id', 'uploaded_by',
        'title', 'category', 'file_path', 'mime_type',
        'file_size', 'description',
    ];

    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function uploader(): BelongsTo  { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function signedUrl(int $minutes = 10): string
    {
        return Storage::disk(config('filesystems.default'))
            ->temporaryUrl($this->file_path, now()->addMinutes($minutes));
    }
}
