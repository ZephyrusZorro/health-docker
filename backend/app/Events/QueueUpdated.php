<?php

namespace App\Events;

use App\Models\Identity\Provider;
use App\Models\Scheduling\Queue as QueueModel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a token is issued, called, or completed. Broadcasts on a
 * public channel so the waiting-room TV and the front-desk UI can update
 * in real time without polling.
 */
class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Provider $provider,
        public readonly QueueModel $queue,
        public readonly string $change, // 'token_issued'|'token_called'|'patient_seen'
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel("queue.provider.{$this->provider->id}")];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'provider_id'   => $this->provider->id,
            'current_token' => $this->queue->current_token,
            'next_token'    => $this->queue->next_token,
            'total_waiting' => $this->queue->total_waiting,
            'total_seen'    => $this->queue->total_seen,
            'change'        => $this->change,
            'at'            => now()->toIso8601String(),
        ];
    }
}
