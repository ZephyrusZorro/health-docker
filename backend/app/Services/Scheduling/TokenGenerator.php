<?php
namespace App\Services\Scheduling;

use App\Models\Scheduling\Queue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * TokenGenerator — allocates the next display token for a provider on a
 * given date, backed by a Queue row that also tracks current/serving token.
 *
 * Tokens are zero-padded with a configurable prefix (default "T", e.g. T001).
 */
class TokenGenerator
{
    /**
     * Issue the next token for a provider on a date.
     * Date defaults to "today" — pass an explicit date for advance bookings.
     */
    public function next(int $providerId, ?string $date = null): string
    {
        $date = $date ?: CarbonImmutable::now()->toDateString();

        return DB::transaction(function () use ($providerId, $date) {
            $queue = Queue::where('provider_id', $providerId)
                ->where('queue_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $queue) {
                $queue = Queue::create([
                    'provider_id'   => $providerId,
                    'queue_date'    => $date,
                    'current_token' => 0,
                    'next_token'    => 1,
                    'total_waiting' => 0,
                    'total_seen'    => 0,
                ]);
            }

            $token = $queue->next_token;
            $queue->update([
                'next_token'    => $token + 1,
                'total_waiting' => $queue->total_waiting + 1,
            ]);

            $prefix = config('hms.identity.token_prefix', 'T');
            return $prefix . str_pad((string) $token, 3, '0', STR_PAD_LEFT);
        });
    }
}
