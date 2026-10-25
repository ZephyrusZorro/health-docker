<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use Illuminate\Http\Request;

/**
 * Role & permission management (SRS §3, §12.1).
 * Permissions themselves are seeded, not user-creatable — the seed file is
 * the source of truth. Roles can be created by Super Admin to mix/match.
 */
class RoleController extends ApiController
{
    public function index()
    {
        return $this->ok(Role::with('permissions:id,name,category')->orderBy('name')->get());
    }

    public function show(Role $role)
    {
        return $this->ok($role->load('permissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:60', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['array'],
            'permissions.*'=> ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name'         => $data['name'],
            'guard_name'   => 'web',
            'display_name' => $data['display_name'],
            'description'  => $data['description'] ?? null,
        ]);
        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }
        return $this->created($role->load('permissions'), 'Role created');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['array'],
            'permissions.*'=> ['string', 'exists:permissions,name'],
        ]);
        $role->update(array_intersect_key($data, array_flip(['display_name', 'description'])));
        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }
        return $this->ok($role->load('permissions'), 'Role updated');
    }

    public function permissions()
    {
        return $this->ok(
            Permission::orderBy('category')->orderBy('name')->get()->groupBy('category')
        );
    }
}
