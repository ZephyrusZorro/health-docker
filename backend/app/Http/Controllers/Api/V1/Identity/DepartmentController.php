<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Identity\Department;
use Illuminate\Http\Request;

class DepartmentController extends ApiController
{
    public function index(Request $request)
    {
        return $this->ok(
            Department::query()
                ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Department::class);
        $facility = app('facility');

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:30'],
            'name'        => ['required', 'string', 'max:100'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
        ]);
        $data['facility_id'] = $facility?->id;
        return $this->created(Department::create($data));
    }

    public function update(Request $request, Department $department)
    {
        $this->authorize('update', $department);
        $department->update($request->only(['name', 'icon', 'description', 'sort_order', 'is_active']));
        return $this->ok($department->fresh());
    }

    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);
        $department->delete();
        return $this->ok(null, 'Department removed');
    }
}
