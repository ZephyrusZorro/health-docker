<?php

namespace App\Console\Commands;

use App\Models\Identity\Facility;
use App\Models\Scheduling\Schedule;
use App\Services\Scheduling\SlotGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generate appointment slots for all active provider schedules.
 *
 * Runs daily at 02:00 and materialises N days of slots so that booking
 * UIs can query them quickly without recomputing from cron patterns.
 */
class GenerateSlots extends Command
{
    protected $signature = 'hms:generate-slots
                            {--days=14 : How many days of slots to generate from today}';

    protected $description = 'Materialise appointment slots for all active schedules';

    public function handle(SlotGenerator $generator): int
    {
        $days = (int) $this->option('days');
        $from = Carbon::today();
        $to   = $from->copy()->addDays($days);

        $this->info("Generating slots from {$from->toDateString()} to {$to->toDateString()}…");

        $created = 0;
        $facilities = Facility::where('is_active', true)->get();

        foreach ($facilities as $facility) {
            $schedules = Schedule::where('facility_id', $facility->id)
                ->where('is_active', true)
                ->get();

            foreach ($schedules as $schedule) {
                $count = $generator->generateForSchedule($schedule, $from, $to);
                $created += $count;
                $this->line(sprintf(
                    '  · %s / provider #%d : %d slots',
                    $facility->code,
                    $schedule->provider_id,
                    $count,
                ));
            }
        }

        $this->info("Done. Generated {$created} slots across " . $facilities->count() . ' facility(s).');
        return self::SUCCESS;
    }
}
