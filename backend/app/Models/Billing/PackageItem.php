<?php
namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageItem extends Model
{
    use HasFactory;

    protected $fillable = ['package_id', 'charge_id', 'quantity'];

    public function package(): BelongsTo { return $this->belongsTo(ChargeCatalog::class, 'package_id'); }
    public function charge(): BelongsTo  { return $this->belongsTo(ChargeCatalog::class, 'charge_id'); }
}
