<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\UserResource;
use App\Models\Identity\Provider;
use App\Models\Identity\Staff;
use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * User & staff management (SRS §4.12 Admin & Configuration).
 * Creates a User row, assigns a role, and back-fills the role-specific
 * Staff or Provider profile in the same transaction.
 */
class UserController extends ApiController
{
    public function index(Request $request)
    {
        $q = User::query()->with(['roles', 'permissions', 'staff.department', 'provider.department'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('role'),   fn ($q) => $q->role($request->role))
            ->when($request->filled('q'),      fn ($q) => $q->where(fn ($q) =>
                $q->where('name',  'ILIKE', "%{$request->q}%")
                  ->orWhere('email','ILIKE', "%{$request->q}%")
            ))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest();

        return UserResource::collection($q->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'password'    => ['required', 'string', 'min:8'],
            'role'        => ['required', 'string', Rule::exists('roles', 'name')],
            'facility_id' => ['required', 'exists:facilities,id'],
            'department_id' => ['nullable', 'exists:departments,id'],

            // For providers
            'provider_type'     => ['nullable', 'in:doctor,nurse,lab_technician,pharmacist,other'],
            'qualification'     => ['nullable', 'string', 'max:150'],
            'specialization'    => ['nullable', 'string', 'max:150'],
            'consultation_fee'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = \DB::transaction(function () use ($data) {
            $user = User::create([
                'facility_id' => $data['facility_id'],
                'name'        => $data['name'],
                'email'       => strtolower($data['email']),
                'phone'       => $data['phone'] ?? null,
                'password'    => Hash::make($data['password']),
                'user_type'   => 'staff',
                'is_active'   => true,
            ]);
            $user->assignRole($data['role']);

            $isProvider = in_array($data['role'], ['doctor', 'nurse', 'lab_technician', 'pharmacist'], true);
            if ($isProvider) {
                Provider::create([
                    'user_id'          => $user->id,
                    'facility_id'      => $data['facility_id'],
                    'department_id'    => $data['department_id'] ?? null,
                    'provider_type'    => $data['provider_type'] ?? $data['role'],
                    'qualification'    => $data['qualification'] ?? null,
                    'specialization'   => $data['specialization'] ?? null,
                    'consultation_fee' => $data['consultation_fee'] ?? null,
                    'is_available_online' => true,
                ]);
            } else {
                Staff::create([
                    'user_id'       => $user->id,
                    'facility_id'   => $data['facility_id'],
                    'department_id' => $data['department_id'] ?? null,
                    'designation'   => $data['role'],
                ]);
            }

            return $user;
        });

        return $this->created(new UserResource($user->load(['roles', 'staff', 'provider'])), 'User created');
    }

    public function show(User $user)
    {
        return $this->ok(new UserResource($user->load(['roles', 'staff.department', 'provider.department'])));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:120'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'password'  => ['sometimes', 'string', 'min:8'],
        ]);
        if (isset($data['password'])) $data['password'] = Hash::make($data['password']);
        $user->update($data);
        return $this->ok(new UserResource($user->fresh()), 'User updated');
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);
        $user->delete(); // soft
        return $this->ok(null, 'User deactivated');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $user->update(['password' => Hash::make($data['password'])]);
        return $this->ok(null, 'Password reset');
    }
}
