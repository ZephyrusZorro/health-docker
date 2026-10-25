<?php

namespace App\Console\Commands;

use App\Models\Audit\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enforce the audit-log retention policy defined in config/hms.php
 * (default 2555 days / 7 years). Logs older than the retention window
 * are archived to JSONL in object storage and then deleted from the
 * hot table — keeping the primary table performant while preserving
 * the records required for regulatory compliance.
 */
class AuditRotate extends Command
{
    protected $signature = 'hms:audit-rotate
                            {--days= : Override retention window (days)}
                            {--dry-run : Count only, do not archive or delete}';

    protected $description = 'Archive and purge audit logs beyond the retention window';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?? config('hms.audit.retention_days', 2555));
        $cutoff = now()->subDays($days);

        $query = AuditLog::where('occurred_at', '<', $cutoff);
        $total = (clone $query)->count();

        $this->info("Audit logs older than {$cutoff->toDateTimeString()}: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no archive or delete performed.');
            return self::SUCCESS;
        }

        // Archive in chunks of 1000 to avoid hydrating the entire table.
        $archiveKey = 'archives/audit/' . now()->format('Y/m/d_His') . '_' . Str::random(6) . '.jsonl';
        $disk       = Storage::disk(config('hms.audit.archive_disk', 'local'));

        $archived = 0;
        $handle = fopen('php://temp', 'w+');

        $query->orderBy('id')->chunk(1000, function ($chunk) use (&$archived, $handle) {
            foreach ($chunk as $entry) {
                fwrite($handle, json_encode($entry->toArray()) . PHP_EOL);
                $archived++;
            }
        });

        rewind($handle);
        $disk->put($archiveKey, stream_get_contents($handle));
        fclose($handle);

        $this->info("Archived {$archived} entries to {$archiveKey}.");

        // Now delete — in chunks so we don't lock the table.
        $deleted = 0;
        AuditLog::where('occurred_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$deleted) {
                $ids = $chunk->pluck('id')->toArray();
                $deleted += AuditLog::whereIn('id', $ids)->delete();
            });

        $this->info("Purged {$deleted} entries from the hot table.");
        return self::SUCCESS;
    }
}
