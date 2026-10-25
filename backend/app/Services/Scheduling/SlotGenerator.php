<?php
namespace App\Services\Scheduling;

use App\Models\Identity\Provider;
use App\Models\Scheduling\ScheduleTemplate;
use App\Models\Scheduling\Slot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Materialises Slot rows from weekly ScheduleTemplates for a date range.
 * Idempotent — re-running won't duplicate (firstOrCreate by composite key).
 *
 * Three entrypoints:
 *   - generateForProvider(Provider, from, to)  — bulk generation for one doctor
 *   - generateForSchedule(ScheduleTemplate, from, to) — one template only
 *   - ensureSlotsFor(Provider, dateString)     — convenience: today/single-day
 */
class SlotGenerator
{
    /** Generate slots for every active template of a provider, across a date range. */
    public function generateForProvider(Provider $provider, CarbonInterface $from, CarbonInterface $to): int
    {
        $templates = $provider->scheduleTemplates()->where('is_active', true)->get();
        if ($templates->isEmpty()) return 0;

        $created = 0;
        DB::transaction(function () use ($templates, $from, $to, &$created) {
            $cursor = CarbonImmutable::parse($from)->startOfDay();
            $end    = CarbonImmutable::parse($to)->startOfDay();
            while ($cursor->lte($end)) {
                foreach ($templates as $template) {
                    if ($this->templateAppliesOn($template, $cursor)) {
                        $created += $this->createSlotsForDate($template, $cursor);
                    }
                }
                $cursor = $cursor->addDay();
            }
        });

        return $created;
    }

    /** Generate slots for one template across a date range. */
    public function generateForSchedule(ScheduleTemplate $template, CarbonInterface $from, CarbonInterface $to): int
    {
        $created = 0;
        DB::transaction(function () use ($template, $from, $to, &$created) {
            $cursor = CarbonImmutable::parse($from)->startOfDay();
            $end    = CarbonImmutable::parse($to)->startOfDay();
            while ($cursor->lte($end)) {
                if ($this->templateAppliesOn($template, $cursor)) {
                    $created += $this->createSlotsForDate($template, $cursor);
                }
                $cursor = $cursor->addDay();
            }
        });
        return $created;
    }

    /** Convenience: ensure slots exist for one doctor on one date (idempotent). */
    public function ensureSlotsFor(Provider $provider, string $date): int
    {
        $when = CarbonImmutable::parse($date)->startOfDay();
        return $this->generateForProvider($provider, $when, $when);
    }

    private function templateAppliesOn(ScheduleTemplate $template, CarbonImmutable $date): bool
    {
        // ISO day-of-week (1=Mon..7=Sun) — matches our seeder convention.
        if ((int) $template->day_of_week !== (int) $date->dayOfWeekIso) return false;

        if ($template->effective_from && $date->lt($template->effective_from)) return false;
        if ($template->effective_to   && $date->gt($template->effective_to))   return false;

        return true;
    }

    protected function createSlotsForDate(ScheduleTemplate $template, CarbonImmutable $date): int
    {
        $start  = CarbonImmutable::parse($date->toDateString() . ' ' . $template->start_time);
        $finish = CarbonImmutable::parse($date->toDateString() . ' ' . $template->end_time);
        $minutes = max(1, (int) $template->slot_minutes);

        $created = 0;
        while ($start->lt($finish)) {
            $slotEnd = $start->addMinutes($minutes);
            if ($slotEnd->gt($finish)) break;

            $slot = Slot::firstOrCreate(
                [
                    'provider_id' => $template->provider_id,
                    'slot_date'   => $date->toDateString(),
                    'start_time'  => $start->format('H:i:s'),
                ],
                [
                    'schedule_template_id' => $template->id,
                    'end_time'             => $slotEnd->format('H:i:s'),
                    'capacity'             => max(1, (int) $template->max_patients_per_slot),
                    'booked_count'         => 0,
                    'status'               => 'available',
                ]
            );
            if ($slot->wasRecentlyCreated) $created++;

            $start = $slotEnd;
        }

        return $created;
    }
}
