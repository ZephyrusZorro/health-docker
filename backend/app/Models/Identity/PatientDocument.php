<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PatientDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'uploaded_by', 'document_type', 'title',
        'file_path', 'mime_type', 'file_size', 'description',
    ];

    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function uploader(): BelongsTo  { return $this->belongsTo(User::class, 'uploaded_by'); }

    /**
     * Signed, time-limited URL for file access — SRS §12.2.
     * No direct public paths, ever.
     */
    public function signedUrl(int $minutes = 10): string
    {
        return Storage::disk(config('filesystems.default'))
            ->temporaryUrl($this->file_path, now()->addMinutes($minutes));
    }
}
