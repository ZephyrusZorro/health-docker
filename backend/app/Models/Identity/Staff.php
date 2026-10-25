<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'staff';

    protected $fillable = [
        'user_id', 'facility_id', 'department_id', 'employee_code',
        'designation', 'date_of_joining', 'emergency_contact',
    ];

    protected function casts(): array
    {
        return [
            'date_of_joining'   => 'date',
            'emergency_contact' => 'array',
        ];
    }

    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function facility(): BelongsTo    { return $this->belongsTo(Facility::class); }
    public function department(): BelongsTo  { return $this->belongsTo(Department::class); }
}
