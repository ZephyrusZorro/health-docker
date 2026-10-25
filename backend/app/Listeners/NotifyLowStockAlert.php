<?php

namespace App\Listeners;

use App\Events\StockLow;
use App\Models\Identity\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLowStockAlert implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $notifier)
    {
    }

    public function handle(StockLow $event): void
    {
        // Notify every active pharmacist + admin in the facility.
        User::where('facility_id', $event->facility->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->hasAnyRole(['pharmacist', 'hospital_admin', 'super_admin']))
            ->each(function (User $u) use ($event) {
                $this->notifier->dispatch(
                    templateKey: 'stock.low',
                    recipient: ['name' => $u->name, 'email' => $u->email, 'user_id' => $u->id],
                    payload: [
                        'medication'     => $event->medication->displayName(),
                        'current_stock'  => $event->currentStock,
                        'reorder_level'  => $event->reorderLevel,
                        'facility'       => $event->facility->name,
                    ],
                    channels: ['email', 'push'],
                );
            });
    }
}
