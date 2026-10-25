<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Events\AppointmentBooked;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\PublicBookAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Identity\Provider;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\Slot;
use App\Services\Identity\UhidGenerator;
use App\Services\Scheduling\SlotGenerator;
use App\Services\Scheduling\TokenGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Online appointment booking from the public website (SRS §4.11).
 *
 * The flow:
 *  1. Patient looks up available slots for a given doctor/date.
 *  2. Patient submits name + phone + email + slot + chief complaint.
 *  3. System finds an existing patient by phone (duplicate detection) or
 *     creates a new one with auto-generated UHID.
 *  4. Appointment row is created and confirmation notification dispatched.
 */
class PublicAppointmentController extends ApiController
{
    public function __construct(
        private readonly UhidGenerator $uhidGen,
        private readonly SlotGenerator $slotGen,
        private readonly TokenGenerator $tokens,
    ) {}

    /**
     * Available slots for a provider on a given date.
     * Regenerates slots from the weekly template on demand (idempotent).
     */
    public function availability(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'date'        => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $provider = Provider::findOrFail($data['provider_id']);
        $this->slotGen->ensureSlotsFor($provider, $data['date']);

        $slots = Slot::where('provider_id', $provider->id)
            ->whereDate('slot_date', $data['date'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->get();

        return $this->ok($slots->map(fn (Slot $s) => [
            'id'           => $s->id,
            'start_time'   => $s->start_time,
            'end_time'     => $s->end_time,
            'capacity'     => $s->capacity,
            'booked_count' => $s->booked_count,
            'available'    => ! $s->isFull() && $s->status === 'available',
        ]));
    }

    public function book(PublicBookAppointmentRequest $request)
    {
        $facility = Facility::firstOrFail();  // single-facility MVP; multi-branch later
        $data     = $request->validated();

        $appt = DB::transaction(function () use ($data, $facility) {
            // --- Duplicate detection (SRS §4.1: intelligent duplicate detection) ---
            $patient = Patient::where('facility_id', $facility->id)
                ->where('phone', $data['phone'])
                ->whereNull('merged_into_patient_id')
                ->first();

            if (! $patient) {
                $patient = Patient::create([
                    'facility_id'    => $facility->id,
                    'uhid'           => $this->uhidGen->generate($facility),
                    'first_name'     => $data['first_name'],
                    'last_name'      => $data['last_name'] ?? null,
                    'phone'          => $data['phone'],
                    'email'          => $data['email'] ?? null,
                    'date_of_birth'  => $data['date_of_birth'],
                    'gender'         => $data['gender'],
                    'payer_type'     => 'self',
                    'registered_at'  => now(),
                ]);
            }

            // --- Slot capacity guard ---
            $slot = Slot::lockForUpdate()->findOrFail($data['slot_id']);
            if ($slot->isFull()) {
                abort(409, 'Slot no longer available. Please choose another.');
            }
            $slot->increment('booked_count');

            $number = 'APT-' . now()->format('Ymd') . '-' . str_pad(
                (string) (Appointment::whereDate('created_at', today())->count() + 1),
                4, '0', STR_PAD_LEFT
            );

            $scheduledStart = \Carbon\Carbon::parse("{$slot->slot_date->toDateString()} {$slot->start_time}");
            $scheduledEnd   = \Carbon\Carbon::parse("{$slot->slot_date->toDateString()} {$slot->end_time}");

            return Appointment::create([
                'facility_id'        => $facility->id,
                'appointment_number' => $number,
                'patient_id'         => $patient->id,
                'provider_id'        => $slot->provider_id,
                'slot_id'            => $slot->id,
                'visit_type_id'      => $data['visit_type_id'] ?? null,
                'scheduled_start'    => $scheduledStart,
                'scheduled_end'      => $scheduledEnd,
                'status'             => Appointment::STATUS_BOOKED,
                'token_number'       => $this->tokens->next($slot->provider_id, $slot->slot_date->toDateString()),
                'booking_source'     => 'public_website',
                'chief_complaint'    => $data['chief_complaint'] ?? null,
            ]);
        });

        event(new AppointmentBooked($appt));
        return $this->created(
            new AppointmentResource($appt->load(['patient', 'provider.user', 'visitType'])),
            'Appointment booked. A confirmation will be sent shortly.'
        );
    }

    /**
     * Anonymous status lookup — patient enters appointment number + phone
     * to check queue position without logging in.
     */
    public function status(Request $request)
    {
        $data = $request->validate([
            'appointment_number' => ['required', 'string'],
            'phone'              => ['required', 'string'],
        ]);

        $appt = Appointment::where('appointment_number', $data['appointment_number'])
            ->whereHas('patient', fn ($q) => $q->where('phone', $data['phone']))
            ->with(['provider.user'])
            ->first();

        if (! $appt) return $this->fail('Appointment not found', 404);

        $queuePosition = Appointment::where('provider_id', $appt->provider_id)
            ->whereDate('scheduled_start', $appt->scheduled_start)
            ->where('status', Appointment::STATUS_CHECKED_IN)
            ->where('token_number', '<', $appt->token_number)
            ->count();

        return $this->ok([
            'appointment_number' => $appt->appointment_number,
            'token'              => $appt->token_number,
            'status'             => $appt->status,
            'doctor'             => $appt->provider->user->name,
            'scheduled_start'    => $appt->scheduled_start,
            'people_ahead'       => $queuePosition,
        ]);
    }
}
