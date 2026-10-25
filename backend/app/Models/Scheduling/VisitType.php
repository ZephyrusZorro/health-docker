<?php
namespace App\Models\Scheduling;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitType extends Model
{
    use HasFactory;
    protected $fillable = ['code', 'name', 'default_duration_minutes', 'color', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
