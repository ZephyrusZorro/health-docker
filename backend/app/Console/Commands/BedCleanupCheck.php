<?php

namespace App\Console\Commands;

use App\Models\Admission\Bed;
use App\Models\Admission\BedStatusHistory;
use App\Models\Identity\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Every 15 minutes, flag any bed that has been in "cleaning" status longer
 * than the configured SLA (config/hms.php → bed.cleaning_minutes, default
 * 45). Housekeeping + nurse supervisors get a notification.
 *
 * Logic: find every bed still in `cleaning` whose latest history row's
 * `start_at` is older than the SLA threshold.
 */
class BedCleanupCheck extends Command
{
    protected $signature = 'hms:bed-cleanup-check';
    protected $description = 'Flag beds stuck in cleaning status beyond the SLA';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $slaMinutes = (int) config('hms.bed.cleaning_minutes', 45);
        $threshold  = now()->subMinutes($slaMinutes);

        // Beds currently in cleaning state...
        $candidates = Bed::with('room.ward')
            ->where('status', Bed::STATUS_CLEANING)
            ->get();

        $stale = $candidates->filter(function (Bed $bed) use ($threshold) {
            $latest = BedStatusHistory::where('bed_id', $bed->id)
                ->where('status', Bed::STATUS_CLEANING)
                ->orderByDesc('start_at')
                ->first();
            return $latest && $latest->start_at && $latest->start_at->lt($threshold);
        });

        $this->info("Beds over cleaning SLA ({$slaMinutes}min): {$stale->count()}");

        if ($stale->isEmpty()) {
            return self::SUCCESS;
        }

        // Notify nurse supervisors + hospital admins.
        $recipients = User::role(['nurse_supervisor', 'nurse', 'hospital_admin'])
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $user) {
            try {
                $dispatcher->send(
                    template:  'housekeeping.bed_cleanup_sla',
                    recipient: $user,
                    data: [
                        'count' => $stale->count(),
                        'beds'  => $stale->map(fn ($b) => [
                            'bed'  => $b->bed_number,
                            'ward' => $b->room->ward->name ?? '—',
                            'room' => $b->room->name ?? '—',
                        ])->values()->toArray(),
                    ],
                    channels: ['email', 'push'],
                );
            } catch (\Throwable $e) {
                $this->warn("  · Notify failed for {$user->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
