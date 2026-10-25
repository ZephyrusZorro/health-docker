<?php
namespace App\Models\Laboratory;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LabResultFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['lab_order_id', 'uploaded_by', 'title', 'file_path', 'mime_type', 'file_size'];

    public function labOrder(): BelongsTo { return $this->belongsTo(LabOrder::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function signedUrl(int $minutes = 10): string
    {
        return Storage::disk(config('filesystems.default'))
            ->temporaryUrl($this->file_path, now()->addMinutes($minutes));
    }
}
