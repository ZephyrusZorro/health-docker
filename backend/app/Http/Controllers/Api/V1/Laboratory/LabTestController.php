<?php

namespace App\Http\Controllers\Api\V1\Laboratory;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Identity\Facility;
use App\Models\Laboratory\LabTest;
use Illuminate\Http\Request;

class LabTestController extends ApiController
{
    public function index(Request $request)
    {
        $q = LabTest::query()
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) =>
                $q->where('name', 'ILIKE', "%{$request->q}%")
                  ->orWhere('code', 'ILIKE', "%{$request->q}%")
            ))
            ->where('is_active', true)
            ->orderBy('name');

        return $q->paginate($request->integer('per_page', 50));
    }

    public function show(LabTest $labTest)
    {
        return $this->ok($labTest->load('panelComponents'));
    }

    public function store(Request $request)
    {
        $facility = app('facility') ?? Facility::firstOrFail();

        $data = $request->validate([
            'code'                     => ['required', 'string', 'max:30'],
            'name'                     => ['required', 'string', 'max:150'],
            'category'                 => ['required', 'string', 'max:60'],
            'test_type'                => ['nullable', 'in:single,panel,radiology'],
            'specimen_type'            => ['nullable', 'string', 'max:60'],
            'default_unit'             => ['nullable', 'string', 'max:30'],
            'reference_low'            => ['nullable', 'numeric'],
            'reference_high'           => ['nullable', 'numeric'],
            'reference_text'           => ['nullable', 'string', 'max:255'],
            'price'                    => ['required', 'numeric', 'min:0'],
            'turnaround_hours'         => ['nullable', 'integer', 'min:0'],
            'preparation_instructions' => ['nullable', 'string'],
        ]);
        $data['facility_id'] = $facility->id;
        $data['test_type']   = $data['test_type'] ?? 'single';

        $test = LabTest::create(array_merge($data, ['is_active' => true]));
        return $this->created($test, 'Test added');
    }

    public function update(Request $request, LabTest $labTest)
    {
        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:150'],
            'category'         => ['sometimes', 'string', 'max:60'],
            'price'            => ['sometimes', 'numeric', 'min:0'],
            'reference_low'    => ['nullable', 'numeric'],
            'reference_high'   => ['nullable', 'numeric'],
            'reference_text'   => ['nullable', 'string', 'max:255'],
            'default_unit'     => ['nullable', 'string', 'max:30'],
            'specimen_type'    => ['nullable', 'string', 'max:60'],
            'is_active'        => ['boolean'],
        ]);
        $labTest->update($data);
        return $this->ok($labTest, 'Test updated');
    }
}
