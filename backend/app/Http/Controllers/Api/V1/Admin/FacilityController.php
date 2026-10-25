<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Identity\Facility;
use Illuminate\Http\Request;

class FacilityController extends ApiController
{
    public function index()
    {
        return $this->ok(Facility::with('departments:id,facility_id,name,code')->orderBy('name')->get());
    }

    public function show(Facility $facility)
    {
        return $this->ok($facility->load('departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:20', 'unique:facilities,code'],
            'name'             => ['required', 'string', 'max:150'],
            'legal_name'       => ['nullable', 'string', 'max:200'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email'],
            'address'          => ['nullable', 'array'],
            'timezone'         => ['nullable', 'string', 'max:64'],
            'default_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $facility = Facility::create(array_merge($data, ['is_active' => true]));
        return $this->created($facility, 'Facility created');
    }

    public function update(Request $request, Facility $facility)
    {
        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:150'],
            'legal_name'       => ['nullable', 'string', 'max:200'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email'],
            'address'          => ['nullable', 'array'],
            'timezone'         => ['nullable', 'string', 'max:64'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'settings'         => ['nullable', 'array'],
            'is_active'        => ['boolean'],
        ]);
        $facility->update($data);
        return $this->ok($facility, 'Facility updated');
    }
}
