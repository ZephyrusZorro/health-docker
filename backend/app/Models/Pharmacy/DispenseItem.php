<?php
namespace App\Models\Pharmacy;

use App\Models\Clinical\Medication;
use App\Models\Clinical\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispenseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispense_id', 'prescription_item_id', 'medicine_batch_id', 'medication_id',
        'medication_name', 'batch_number', 'expiry_date',
        'quantity', 'unit_price', 'tax_percent', 'tax_amount',
        'discount_amount', 'line_total', 'is_substituted',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'     => 'date',
            'unit_price'      => 'decimal:2',
            'tax_percent'     => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total'      => 'decimal:2',
            'is_substituted'  => 'boolean',
        ];
    }

    public function dispense(): BelongsTo         { return $this->belongsTo(Dispense::class); }
    public function prescriptionItem(): BelongsTo { return $this->belongsTo(PrescriptionItem::class); }
    public function batch(): BelongsTo            { return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id'); }
    public function medication(): BelongsTo       { return $this->belongsTo(Medication::class); }
}
