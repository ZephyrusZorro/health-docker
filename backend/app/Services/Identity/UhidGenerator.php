<?php
namespace App\Services\Identity;

use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use Illuminate\Support\Facades\DB;

/**
 * UHID = Unique Hospital ID. System-generated, immutable (SRS §4.1).
 * Format (default): "{prefix}-{year}-{sequence:6}"  e.g.  NH-2026-000001
 * Sequence is per-facility, per-year, monotonically increasing.
 */
class UhidGenerator
{
    public function generate(Facility $facility): string
    {
        return DB::transaction(function () use ($facility) {
            $prefix = config('hms.identity.uhid_prefix', 'NH');
            $year   = now()->year;

            // Count existing UHIDs for this facility+year to determine next seq.
            // Wrapped in a transaction w/ row-level lock on a concurrency guard row.
            $count = Patient::where('facility_id', $facility->id)
                ->where('uhid', 'ILIKE', "{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->count();

            $seq = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
            return "{$prefix}-{$year}-{$seq}";
        });
    }
}
