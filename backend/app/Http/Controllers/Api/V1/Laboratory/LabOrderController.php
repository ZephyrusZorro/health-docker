<?php

namespace App\Http\Controllers\Api\V1\Laboratory;

use App\Events\LabResultReady;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StoreLabOrderRequest;
use App\Http\Requests\StoreLabResultRequest;
use App\Models\Identity\Facility;
use App\Models\Laboratory\LabOrder;
use App\Models\Laboratory\LabOrderTest;
use App\Models\Laboratory\LabResult;
use App\Models\Laboratory\LabSample;
use App\Models\Laboratory\LabTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lab workflow (SRS §4.8, §8.5):
 *   Order placed → Sample collected → In-Process → Result entered →
 *   Abnormals flagged → Doctor notified → Patient chart updated
 */
class LabOrderController extends ApiController
{
    public function index(Request $request)
    {
        $q = LabOrder::query()
            ->with(['patient:id,uhid,first_name,last_name', 'tests.labTest:id,code,name,default_unit'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('patient_id'),   fn ($q) => $q->where('patient_id', $request->patient_id))
            ->when($request->filled('status'),       fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('encounter_id'), fn ($q) => $q->where('encounter_id', $request->encounter_id))
            ->latest('ordered_at');

        return $q->paginate($request->integer('per_page', 25));
    }

    public function store(StoreLabOrderRequest $request)
    {
        $facility = app('facility') ?? Facility::firstOrFail();
        $data     = $request->validated();

        $order = DB::transaction(function () use ($data, $facility) {
            $order = LabOrder::create([
                'facility_id'      => $facility->id,
                'lab_order_number' => $this->nextOrderNumber(),
                'patient_id'       => $data['patient_id'],
                'encounter_id'     => $data['encounter_id'] ?? null,
                'ordered_by'       => auth()->id(),
                'ordered_at'       => now(),
                'priority'         => $data['priority'] ?? 'routine',
                'clinical_notes'   => $data['clinical_note'] ?? $data['clinical_notes'] ?? null,
                'status'           => 'ordered',
            ]);

            foreach ($data['test_ids'] as $testId) {
                $test = LabTest::findOrFail($testId);
                LabOrderTest::create([
                    'lab_order_id'    => $order->id,
                    'lab_test_id'     => $test->id,
                    'test_name'       => $test->name,
                    'price_snapshot'  => $test->price,
                    'status'          => 'pending',
                ]);
            }
            return $order;
        });

        return $this->created($order->load('tests.labTest'), 'Lab order placed');
    }

    public function show(LabOrder $labOrder)
    {
        return $this->ok($labOrder->load([
            'tests.labTest', 'tests.result.files', 'samples', 'patient', 'orderedBy',
        ]));
    }

    /** Lab tech logs sample collection. */
    public function collectSample(Request $request, LabOrder $labOrder)
    {
        $data = $request->validate([
            'specimen_type'   => ['required', 'string', 'max:60'],
            'container_type'  => ['nullable', 'string', 'max:60'],
            'collected_at'    => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $sample = LabSample::create([
            'sample_number'  => 'SMP-' . strtoupper(bin2hex(random_bytes(3))),
            'lab_order_id'   => $labOrder->id,
            'patient_id'     => $labOrder->patient_id,
            'specimen_type'  => $data['specimen_type'],
            'container_type' => $data['container_type'] ?? null,
            'collected_by'   => auth()->id(),
            'collected_at'   => $data['collected_at'] ?? now(),
            'status'         => 'collected',
            'notes'          => $data['notes'] ?? null,
        ]);

        // Advance the order if still in initial state.
        if ($labOrder->status === 'ordered' || $labOrder->status === 'sample_collection_pending') {
            $labOrder->update(['status' => 'sample_collected']);
        }
        // Advance any pending order_tests
        $labOrder->tests()->where('status', 'pending')->update(['status' => 'sample_collected']);

        return $this->created($sample, 'Sample collected');
    }

    /** Lab tech enters numeric/text result for a single test component. */
    public function storeResult(StoreLabResultRequest $request, LabOrderTest $labOrderTest)
    {
        $data = $request->validated();
        $test = $labOrderTest->labTest;

        $value = $data['value_numeric'] ?? null;
        $interpretation = $this->interpret(
            $value,
            $test->reference_low,
            $test->reference_high,
        );

        $result = DB::transaction(function () use ($labOrderTest, $test, $data, $interpretation, $value) {
            $result = LabResult::updateOrCreate(
                ['lab_order_test_id' => $labOrderTest->id],
                [
                    'lab_order_id'      => $labOrderTest->lab_order_id,
                    'lab_test_id'       => $test->id,
                    'entered_by'        => auth()->id(),
                    'parameter_name'    => $test->name,
                    'value_numeric'     => $value,
                    'value_string'      => $data['value_text'] ?? $data['value_string'] ?? null,
                    'unit'              => $data['unit'] ?? $test->default_unit,
                    'reference_low'     => $test->reference_low,
                    'reference_high'    => $test->reference_high,
                    'reference_text'    => $test->reference_text,
                    'interpretation'    => $interpretation,
                    'is_abnormal'       => in_array($interpretation, ['low', 'high', 'critical_low', 'critical_high', 'abnormal'], true),
                    'status'            => 'final',
                    'technician_notes'  => $data['interpretation'] ?? null,
                    'result_date'       => now(),
                ]
            );

            $labOrderTest->update(['status' => 'completed']);

            // Flip the parent order if every test is done.
            $pending = $labOrderTest->labOrder->tests()->where('status', '!=', 'completed')->count();
            if ($pending === 0) {
                $labOrderTest->labOrder->update(['status' => 'completed', 'completed_at' => now()]);
                event(new LabResultReady($labOrderTest->labOrder->fresh()->load('tests.result')));
            }
            return $result;
        });

        return $this->created($result, 'Result recorded');
    }

    private function interpret(?float $value, ?float $low, ?float $high): ?string
    {
        if ($value === null) return null;
        if ($low !== null  && $value < (float) $low)  return 'low';
        if ($high !== null && $value > (float) $high) return 'high';
        if ($low === null && $high === null)          return null;
        return 'normal';
    }

    /** Verifying clinician signs off on a result. */
    public function verifyResult(LabResult $labResult)
    {
        $labResult->update([
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'status'      => 'final',
        ]);
        return $this->ok($labResult, 'Result verified');
    }

    private function nextOrderNumber(): string
    {
        $year = now()->year;
        $next = (int) (LabOrder::whereYear('created_at', $year)->count() + 1);
        return sprintf('LAB-%d-%06d', $year, $next);
    }
}
