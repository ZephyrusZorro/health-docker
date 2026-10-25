<?php

namespace App\Events;

use App\Models\Admission\BedAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts so the bed-occupancy wall-board updates in real time.
 */
class BedAssigned implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly BedAssignment $assignment)
    {
    }

    public function broadcastOn(): array
    {
        // Resolve facility through the bed (BedAssignment doesn't carry facility_id directly).
        $facilityId = $this->assignment->bed?->facility_id ?? 0;
        return [new Channel("facility.{$facilityId}.beds")];
    }

    public function broadcastAs(): string
    {
        return 'bed.assigned';
    }
}
