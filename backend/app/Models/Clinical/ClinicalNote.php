<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalNote extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'encounter_id', 'patient_id', 'author_id', 'note_type',
        'title', 'content', 'structured_content',
        'is_signed', 'signed_at', 'signed_by',
    ];

    protected function casts(): array
    {
        return [
            'structured_content' => 'array',
            'is_signed'          => 'boolean',
            'signed_at'          => 'datetime',
        ];
    }

    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function author(): BelongsTo    { return $this->belongsTo(User::class, 'author_id'); }
    public function signer(): BelongsTo    { return $this->belongsTo(User::class, 'signed_by'); }
}
