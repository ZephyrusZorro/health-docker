<?php

namespace App\Models\Identity;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A User is the authentication subject. Every Staff, Provider (doctor/nurse),
 * and Patient with portal access is backed by a User row. Role assignment
 * determines what they can see and do (SRS §3, §12.1).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'facility_id', 'name', 'email', 'phone', 'password',
        'avatar_url', 'user_type', 'is_active', 'preferences',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token', 'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'preferences'       => 'array',
            'is_active'         => 'boolean',
            'mfa_enabled'       => 'boolean',
        ];
    }

    // ---- Relations ----------------------------------------------------------

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    // ---- Accessors / Scopes -------------------------------------------------

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function isDoctor(): bool
    {
        return $this->hasRole('doctor');
    }

    public function isStaffMember(): bool
    {
        return in_array($this->user_type, ['staff', 'system'], true);
    }
}
