<?php

namespace App\Http\Controllers\Api\V1\Admission;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\BedResource;
use App\Models\Admission\Bed;
use App\Models\Admission\BedStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Visual bed occupancy dashboard — SRS §4.10.
 */
class BedController extends ApiController
{
    public function index(Request $request)
    {
        $q = Bed::query()
            ->with(['room.ward'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('ward_id'), fn ($q) => $q->where('ward_id', $request->ward_id))
            ->when($request->filled('status'),  fn ($q) => $q->where('status', $request->status));

        return BedResource::collection($q->paginate($request->integer('per_page', 200)));
    }

    /** Aggregated occupancy summary for the wards page. */
    public function occupancyDashboard()
    {
        $beds = Bed::query()
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->get();

        $total = $beds->count();
        $occupied = $beds->where('status', Bed::STATUS_OCCUPIED)->count();

        return $this->ok([
            'total'             => $total,
            'available'         => $beds->where('status', Bed::STATUS_AVAILABLE)->count(),
            'occupied'          => $occupied,
            'cleaning'          => $beds->where('status', Bed::STATUS_CLEANING)->count(),
            'maintenance'       => $beds->where('status', Bed::STATUS_MAINTENANCE)->count(),
            'reserved'          => $beds->where('status', Bed::STATUS_RESERVED)->count(),
            'blocked'           => $beds->where('status', Bed::STATUS_BLOCKED)->count(),
            'occupancy_percent' => $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
        ]);
    }

    /**
     * Manual status change (housekeeping, maintenance, etc.).
     * Always closes-out the previous BedStatusHistory row and opens a new one.
     */
    public function updateStatus(Request $request, Bed $bed)
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,occupied,cleaning,maintenance,reserved,blocked'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($bed, $data) {
            $previous = $bed->status;

            // Close any open history row.
            BedStatusHistory::where('bed_id', $bed->id)
                ->whereNull('end_at')
                ->update(['end_at' => now()]);

            $bed->update(['status' => $data['status']]);

            BedStatusHistory::create([
                'bed_id'          => $bed->id,
                'changed_by'      => auth()->id(),
                'status'          => $data['status'],
                'previous_status' => $previous,
                'start_at'        => now(),
                'reason'          => $data['reason'] ?? null,
            ]);
        });

        return $this->ok(new BedResource($bed->fresh(['room.ward'])), 'Status updated');
    }
}
