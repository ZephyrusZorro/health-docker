<?php
namespace App\Models\Laboratory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabOrderTest extends Model
{
    use HasFactory;

    protected $fillable = ['lab_order_id', 'lab_test_id', 'test_name', 'price_snapshot', 'status'];

    protected function casts(): array { return ['price_snapshot' => 'decimal:2']; }

    public function labOrder(): BelongsTo { return $this->belongsTo(LabOrder::class); }
    public function labTest(): BelongsTo  { return $this->belongsTo(LabTest::class); }
    public function result(): HasOne      { return $this->hasOne(LabResult::class, 'lab_order_test_id'); }
}
