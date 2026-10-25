<?php

namespace App\Http\Controllers\Api\V1\Pharmacy;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\DispenseRequest;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Pharmacy\Dispense;
use App\Services\Pharmacy\FefoDispenser;
use Illuminate\Http\Request;

class DispenseController extends ApiController
{
    public function __construct(private readonly FefoDispenser $dispenser)
    {
    }

    public function index(Request $request)
    {
        $q = Dispense::query()
            ->with(['patient', 'items.medication'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->patient_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('dispensed_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('dispensed_at', '<=', $request->to))
            ->latest('dispensed_at');

        return $q->paginate($request->integer('per_page', 20));
    }

    public function store(DispenseRequest $request)
    {
        $facility = app('facility') ?? Facility::firstOrFail();
        $patient  = Patient::findOrFail($request->input('patient_id'));

        try {
            $dispense = $this->dispenser->dispense(
                facility: $facility,
                patient:  $patient,
                items:    $request->input('items', []),
                prescriptionId: $request->input('prescription_id'),
                encounterId:    $request->input('encounter_id'),
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        }

        $dispense->load(['items.medication', 'items.batch', 'patient']);
        return $this->created($dispense, 'Medicines dispensed');
    }

    public function show(Dispense $dispense)
    {
        $dispense->load(['items.medication', 'items.batch', 'patient']);
        return $this->ok($dispense);
    }
}
