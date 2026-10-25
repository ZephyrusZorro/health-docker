<?php
namespace App\Models\Clinical;

use App\Models\Identity\Patient;
use App\Models\Identity\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** FHIR ServiceRequest — lab, imaging, procedure orders. */
class Order extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'order_number', 'facility_id', 'encounter_id', 'patient_id',
        'ordered_by', 'order_type', 'priority', 'status',
        'reason', 'notes', 'ordered_at', 'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at'   => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo   { return $this->belongsTo(Patient::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(Encounter::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(User::class, 'ordered_by'); }
    public function items(): HasMany       { return $this->hasMany(OrderItem::class); }
}
