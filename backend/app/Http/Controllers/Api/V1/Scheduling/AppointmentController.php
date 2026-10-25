<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\QueueUpdated;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Identity\Facility;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\Queue as QueueModel;
use App\Models\Scheduling\Slot;
use App\Services\Scheduling\TokenGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends ApiController
{
    public function __construct(private readonly TokenGenerator $tokens)
    {
    }

    public function index(Request $request)
    {
        $q = Appointment::query()
            ->with(['patient', 'provider.user', 'visitType'])
            ->when(app()->has('facility'), fn ($q) => $q->where('facility_id', app('facility')->id))
            ->when($request->filled('date'),
                fn ($q) => $q->whereDate('scheduled_start', $request->date)
            )
            ->when($request->filled('provider_id'),
                fn ($q) => $q->where('provider_id', $request->provider_id)
            )
            ->when($request->filled('patient_id'),
                fn ($q) => $q->where('patient_id', $request->patient_id)
            )
            ->when($request->filled('status'),
                fn ($q) => $q->where('status', $request->status)
            )
            ->when($request->boolean('today'), fn ($q) => $q->today())
            ->orderBy('scheduled_start');

        return AppointmentResource::collection(
            $q->paginate($request->integer('per_page', 25))
        );
    }

    public function store(StoreAppointmentRequest $request)
    {
        $facility = app('facility') ?? Facility::firstOrFail();
        $data = $request->validated();

        $appt = DB::transaction(function () use ($data, $facility) {
            // Concurrency guard: if a slot is attached, lock it and check capacity.
            if (! empty($data['slot_id'])) {
                $slot = Slot::lockForUpdate()->findOrFail($data['slot_id']);
                if ($slot->isFull()) {
                    abort(409, 'Selected slot is fully booked.');
                }
                $slot->increment('booked_count');
            }

            $number = 'APT-' . now()->format('Ymd') . '-' . str_pad(
                (string) (Appointment::whereDate('created_at', today())->count() + 1),
                4, '0', STR_PAD_LEFT
            );

            return Appointment::create(array_merge($data, [
                'facility_id'         => $facility->id,
                'appointment_number'  => $number,
                'token_number'        => $this->tokens->next($data['provider_id'], now()->toDateString()),
                'status'              => Appointment::STATUS_BOOKED,
                'booking_source'      => $data['booking_source'] ?? 'walk_in',
                'created_by'          => auth()->id(),
            ]));
        });

        event(new AppointmentBooked($appt));

        return $this->created(
            new AppointmentResource($appt->load(['patient', 'provider.user', 'visitType'])),
            'Appointment booked'
        );
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['patient', 'provider.user', 'visitType', 'encounter']);
        return $this->ok(new AppointmentResource($appointment));
    }

    /**
     * Front-desk check-in. Transitions booked → checked_in and lights up
     * the provider queue for real-time display.
     */
    public function checkIn(Appointment $appointment)
    {
        $this->authorize('checkIn', $appointment);

        if (! $appointment->canTransitionTo(Appointment::STATUS_CHECKED_IN)) {
            return $this->fail("Cannot check in from status '{$appointment->status}'.", 422);
        }

        $appointment->update([
            'status'        => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);

        $queue = QueueModel::firstOrCreate(
            ['provider_id' => $appointment->provider_id, 'queue_date' => today()],
            ['current_token' => 0, 'next_token' => 1, 'total_waiting' => 0, 'total_seen' => 0]
        );
        $queue->increment('total_waiting');
        event(new QueueUpdated($appointment->provider, $queue->fresh(), 'token_issued'));

        return $this->ok(new AppointmentResource($appointment->fresh()->load('patient')), 'Checked in');
    }

    public function startConsult(Appointment $appointment)
    {
        $this->authorize('startConsult', $appointment);

        if (! $appointment->canTransitionTo(Appointment::STATUS_IN_CONSULT)) {
            return $this->fail("Cannot start consult from status '{$appointment->status}'.", 422);
        }

        $appointment->update([
            'status'             => Appointment::STATUS_IN_CONSULT,
            'consult_started_at' => now(),
        ]);

        $queue = QueueModel::where('provider_id', $appointment->provider_id)
            ->whereDate('queue_date', today())->first();
        if ($queue) {
            $queue->update(['current_token' => $appointment->token_number]);
            event(new QueueUpdated($appointment->provider, $queue, 'token_called'));
        }

        return $this->ok(new AppointmentResource($appointment->fresh()), 'Consultation started');
    }

    public function complete(Appointment $appointment)
    {
        $this->authorize('complete', $appointment);

        if (! $appointment->canTransitionTo(Appointment::STATUS_COMPLETED)) {
            return $this->fail("Cannot complete from status '{$appointment->status}'.", 422);
        }

        $appointment->update([
            'status'            => Appointment::STATUS_COMPLETED,
            'consult_ended_at'  => now(),
        ]);

        $queue = QueueModel::where('provider_id', $appointment->provider_id)
            ->whereDate('queue_date', today())->first();
        if ($queue) {
            $queue->increment('total_seen');
            $queue->decrement('total_waiting');
            event(new QueueUpdated($appointment->provider, $queue->fresh(), 'patient_seen'));
        }

        return $this->ok(new AppointmentResource($appointment->fresh()), 'Consultation completed');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $this->authorize('cancel', $appointment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        if (! $appointment->canTransitionTo(Appointment::STATUS_CANCELLED)) {
            return $this->fail("Cannot cancel from status '{$appointment->status}'.", 422);
        }

        $appointment->update([
            'status'              => Appointment::STATUS_CANCELLED,
            'cancellation_reason' => $data['reason'] ?? null,
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
        ]);

        // Free the slot back up.
        if ($appointment->slot) {
            $appointment->slot->decrement('booked_count');
        }

        event(new AppointmentCancelled($appointment, $data['reason'] ?? null));

        return $this->ok(null, 'Appointment cancelled');
    }

    public function markNoShow(Appointment $appointment)
    {
        $this->authorize('markNoShow', $appointment);

        if (! $appointment->canTransitionTo(Appointment::STATUS_NO_SHOW)) {
            return $this->fail("Cannot mark no-show from '{$appointment->status}'.", 422);
        }

        $appointment->update(['status' => Appointment::STATUS_NO_SHOW]);
        return $this->ok(null, 'Marked no-show');
    }
}
