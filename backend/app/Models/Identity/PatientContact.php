<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'name', 'relationship', 'phone', 'email',
        'address', 'is_emergency_contact', 'can_receive_updates',
    ];

    protected function casts(): array
    {
        return [
            'address'             => 'array',
            'is_emergency_contact' => 'boolean',
            'can_receive_updates'  => 'boolean',
        ];
    }

    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
