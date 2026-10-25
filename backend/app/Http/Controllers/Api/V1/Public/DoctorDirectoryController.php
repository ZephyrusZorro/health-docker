<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ProviderResource;
use App\Models\Identity\Department;
use App\Models\Identity\Provider;
use Illuminate\Http\Request;

/**
 * Public endpoints for the hospital website — doctor directory, department
 * info, available slots. No auth required; rate-limited in routes file.
 */
class DoctorDirectoryController extends ApiController
{
    public function index(Request $request)
    {
        $q = Provider::query()
            ->doctors()
            ->availableOnline()
            ->with(['user:id,name,avatar_url', 'department:id,name,icon'])
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('specialization'), fn ($q) => $q->where('specialization', 'ILIKE', "%{$request->specialization}%"))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('user', fn ($u) =>
                $u->where('name', 'ILIKE', "%{$request->q}%"))
            )
            ->orderBy('id');

        return ProviderResource::collection($q->paginate($request->integer('per_page', 20)));
    }

    public function show(Provider $provider)
    {
        return $this->ok(new ProviderResource(
            $provider->load(['user:id,name,avatar_url', 'department:id,name,icon'])
        ));
    }

    public function departments()
    {
        return $this->ok(
            Department::where('is_active', true)
                ->with(['providers' => fn ($q) => $q->doctors()->availableOnline()->with('user:id,name,avatar_url')])
                ->orderBy('sort_order')->orderBy('name')->get()
        );
    }
}
