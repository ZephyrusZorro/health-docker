<?php

namespace App\Http\Controllers\Api\V1\Pharmacy;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\MedicationResource;
use App\Models\Clinical\Medication;
use App\Models\Pharmacy\MedicineBatch;
use Illuminate\Http\Request;

/**
 * Medication catalog (+ per-medication current stock summary).
 */
class MedicationController extends ApiController
{
    public function index(Request $request)
    {
        $facilityId = app('facility')?->id;

        $q = Medication::query()
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->orderBy('generic_name');

        $meds = $q->paginate($request->integer('per_page', 20));

        // Attach aggregate live stock per medication for the current facility.
        if ($facilityId) {
            $ids = $meds->pluck('id');
            $stockMap = MedicineBatch::query()
                ->whereIn('medication_id', $ids)
                ->where('facility_id', $facilityId)
                ->where('expiry_date', '>', now())
                ->selectRaw('medication_id, SUM(current_quantity) as total')
                ->groupBy('medication_id')
                ->pluck('total', 'medication_id');

            $meds->getCollection()->transform(function ($m) use ($stockMap) {
                $m->total_stock = (int) ($stockMap[$m->id] ?? 0);
                return $m;
            });
        }

        return MedicationResource::collection($meds);
    }

    public function show(Medication $medication)
    {
        $facilityId = app('facility')?->id;
        $batches = $medication->batches()
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId))
            ->where('current_quantity', '>', 0)
            ->orderBy('expiry_date')
            ->get();

        return $this->ok([
            'medication' => new MedicationResource($medication),
            'batches'    => $batches,
            'total_stock'=> (int) $batches->sum('current_quantity'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Medication::class);
        $data = $request->validate([
            'code'         => ['required', 'string', 'max:50', 'unique:medications,code'],
            'generic_name' => ['required', 'string', 'max:150'],
            'brand_name'   => ['nullable', 'string', 'max:150'],
            'strength'     => ['nullable', 'string', 'max:50'],
            'form'         => ['nullable', 'string', 'max:50'],
            'category'     => ['nullable', 'string', 'max:50'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'default_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_prescription_required' => ['sometimes', 'boolean'],
            'is_controlled' => ['sometimes', 'boolean'],
        ]);
        return $this->created(Medication::create($data));
    }

    public function update(Request $request, Medication $medication)
    {
        $this->authorize('update', $medication);
        $medication->update($request->all());
        return $this->ok(new MedicationResource($medication->fresh()));
    }
}
