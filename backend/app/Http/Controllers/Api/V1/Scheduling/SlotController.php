<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Identity\Provider;
use App\Models\Scheduling\Slot;
use App\Services\Scheduling\SlotGenerator;
use Illuminate\Http\Request;

/**
 * Slot discovery for booking UIs. Lazily materialises slots from templates
 * if none exist for the requested date yet (see SlotGenerator).
 */
class SlotController extends ApiController
{
    public function __construct(private readonly SlotGenerator $generator)
    {
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'provider_id'  => ['required', 'integer', 'exists:providers,id'],
            'date'         => ['required', 'date', 'after_or_equal:today'],
            'only_available' => ['sometimes', 'boolean'],
        ]);

        $provider = Provider::findOrFail($data['provider_id']);
        $date     = \Carbon\Carbon::parse($data['date']);

        // Lazy materialisation — run the generator if no slots exist for the date.
        if (! Slot::where('provider_id', $provider->id)
                ->whereDate('slot_date', $date)->exists()) {
            $this->generator->ensureSlotsFor($provider, $date->toDateString());
        }

        $slots = Slot::query()
            ->where('provider_id', $provider->id)
            ->whereDate('slot_date', $date)
            ->when($data['only_available'] ?? true, fn ($q) => $q->whereColumn('booked_count', '<', 'capacity'))
            ->orderBy('start_time')
            ->get(['id', 'slot_date', 'start_time', 'end_time', 'capacity', 'booked_count', 'status']);

        return $this->ok($slots);
    }
}
