<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ProviderResource;
use App\Models\Identity\Provider;
use Illuminate\Http\Request;

class ProviderController extends ApiController
{
    public function index(Request $request)
    {
        $q = Provider::query()
            ->with(['user', 'department'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('provider_type'), fn ($q) => $q->where('provider_type', $request->provider_type))
            ->when($request->filled('search'), fn ($q) =>
                $q->whereHas('user', fn ($u) => $u->where('name', 'ILIKE', '%'.$request->search.'%'))
            );

        return ProviderResource::collection($q->paginate($request->integer('per_page', 20)));
    }

    public function show(Provider $provider)
    {
        return $this->ok(new ProviderResource($provider->load(['user', 'department'])));
    }

    public function update(Request $request, Provider $provider)
    {
        $this->authorize('update', $provider);

        $data = $request->validate([
            'specialization'      => ['sometimes', 'nullable', 'string', 'max:150'],
            'qualification'       => ['sometimes', 'nullable', 'string', 'max:150'],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'consultation_fee'    => ['sometimes', 'numeric', 'min:0'],
            'bio'                 => ['sometimes', 'nullable', 'string'],
            'languages'           => ['sometimes', 'nullable', 'array'],
            'is_available_online' => ['sometimes', 'boolean'],
            'department_id'       => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
        ]);
        $provider->update($data);
        return $this->ok(new ProviderResource($provider->fresh(['user', 'department'])));
    }

    /**
     * Today's queue snapshot for a doctor's dashboard (SRS §4.5 Doctor Module).
     */
    public function todayDashboard(Provider $provider)
    {
        $today = today();
        $appts = $provider->appointments()
            ->whereDate('scheduled_start', $today)
            ->with(['patient'])
            ->orderBy('scheduled_start')
            ->get();

        return $this->ok([
            'provider_id'     => $provider->id,
            'total_today'     => $appts->count(),
            'by_status'       => $appts->groupBy('status')->map->count(),
            'waiting'         => $appts->where('status', 'checked_in')->values(),
            'in_consultation' => $appts->where('status', 'in_consultation')->values(),
            'upcoming'        => $appts->where('status', 'booked')->values(),
            'completed'       => $appts->where('status', 'completed')->count(),
        ]);
    }
}
