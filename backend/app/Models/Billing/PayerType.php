<?php
namespace App\Models\Billing;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerType extends Model
{
    use HasFactory;

    protected $fillable = ['facility_id', 'code', 'name', 'type', 'discount_percent', 'requires_approval', 'is_active'];

    protected function casts(): array
    {
        return [
            'discount_percent'  => 'decimal:2',
            'requires_approval' => 'boolean',
            'is_active'         => 'boolean',
        ];
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
}
