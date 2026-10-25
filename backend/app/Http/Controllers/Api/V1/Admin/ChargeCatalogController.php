<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Billing\ChargeCatalog;
use Illuminate\Http\Request;

/**
 * Fee & charge catalog — consultations, procedures, packages, admin fees
 * (SRS §4.12). Seeded with defaults; admin can edit/extend.
 */
class ChargeCatalogController extends ApiController
{
    public function index(Request $request)
    {
        $q = ChargeCatalog::query()
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) =>
                $q->where('name', 'ILIKE', "%{$request->q}%")
                  ->orWhere('code', 'ILIKE', "%{$request->q}%")
            ))
            ->orderBy('category')->orderBy('name');

        return $q->paginate($request->integer('per_page', 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'code'        => ['required', 'string', 'max:40'],
            'name'        => ['required', 'string', 'max:150'],
            'category'    => ['required', 'in:consultation,procedure,room_charge,nursing_charge,emergency,ambulance,package,registration,other'],
            'unit_price'  => ['required', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $item = ChargeCatalog::create(array_merge($data, ['is_active' => true]));
        return $this->created($item, 'Charge added');
    }

    public function update(Request $request, ChargeCatalog $chargeCatalog)
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:150'],
            'unit_price'  => ['sometimes', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ]);
        $chargeCatalog->update($data);
        return $this->ok($chargeCatalog, 'Charge updated');
    }
}
