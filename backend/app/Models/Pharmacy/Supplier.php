<?php
namespace App\Models\Pharmacy;

use App\Models\Identity\Facility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id', 'code', 'name', 'contact_person',
        'phone', 'email', 'address', 'gstin', 'license_number',
        'payment_terms_days', 'is_active',
    ];

    protected function casts(): array
    {
        return ['address' => 'array', 'is_active' => 'boolean'];
    }

    public function facility(): BelongsTo     { return $this->belongsTo(Facility::class); }
    public function batches(): HasMany        { return $this->hasMany(MedicineBatch::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
}
