<?php

namespace Database\Seeders;

use App\Models\Admission\Admission;
use App\Models\Admission\Bed;
use App\Models\Admission\BedAssignment;
use App\Models\Admission\BedStatusHistory;
use App\Models\Admission\DischargeSummary;
use App\Models\Billing\Invoice;
use App\Models\Billing\Payment;
use App\Models\Clinical\Encounter;
use App\Models\Clinical\Medication;
use App\Models\Clinical\Observation;
use App\Models\Clinical\Prescription;
use App\Models\Clinical\PrescriptionItem;
use App\Models\Identity\Department;
use App\Models\Identity\Facility;
use App\Models\Identity\Patient;
use App\Models\Identity\Provider;
use App\Models\Laboratory\LabOrder;
use App\Models\Laboratory\LabOrderTest;
use App\Models\Laboratory\LabResult;
use App\Models\Laboratory\LabSample;
use App\Models\Laboratory\LabTest;
use App\Models\Pharmacy\Dispense;
use App\Models\Pharmacy\DispenseItem;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Pharmacy\StockMovement;
use App\Models\Pharmacy\Supplier;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\ScheduleTemplate;
use App\Models\Scheduling\Slot;
use App\Models\Scheduling\VisitType;
use App\Services\Billing\InvoiceAggregator;
use App\Services\Identity\UhidGenerator;
use App\Services\Scheduling\SlotGenerator;
use App\Services\Scheduling\TokenGenerator;
use Carbon\CarbonImmutable;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Realistic end-to-end demo data (SRS §15.3).
 *
 *   - Suppliers + medicine batches so pharmacy has stock for FEFO
 *   - 30 en_IN patients with demographics, contacts, payer types
 *   - Weekly schedule templates per doctor; slots for next 14 days
 *   - 30 historical appointments (15 completed with vitals/Rx/lab)
 *   - 15 upcoming appointments (booked / confirmed / checked-in)
 *   - 3 IPD admissions (1 active, 2 discharged)
 *   - 10 invoices in varied payment states
 *
 * The seeder is permissive: it skips gracefully if any prerequisite
 * (e.g. a doctor for a department) is missing.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create('en_IN');
        $facility = Facility::firstOrFail();

        // ------------------------------------------------------------------
        // 1. Pharmacy supply chain — supplier + initial batches per medicine
        // ------------------------------------------------------------------
        $supplier = Supplier::firstOrCreate(
            ['facility_id' => $facility->id, 'code' => 'SUP-001'],
            [
                'facility_id'  => $facility->id,
                'name'         => 'MedSupply Distributors Pvt Ltd',
                'contact_person' => 'R. Krishnan',
                'phone'        => '+91 98000 22222',
                'email'        => 'orders@medsupply.example',
                'gstin'        => '27ABCDE1234F1Z5',
                'license_number' => 'TG-WH-2024-0789',
                'payment_terms_days' => 30,
                'is_active'    => true,
            ]
        );

        $medications = Medication::all();
        $systemUserId = \App\Models\Identity\User::where('email', 'pharmacy@nishat.local')->value('id')
            ?? \App\Models\Identity\User::value('id');

        foreach ($medications as $med) {
            // Two batches per medicine: one expiring in ~6 months, one in ~2 years.
            for ($i = 1; $i <= 2; $i++) {
                $expiry = now()->addMonths($i === 1 ? 6 : 24)->toDateString();
                $batchNumber = sprintf('B%03d-%s-%02d', $med->id, now()->format('y'), $i);

                $batch = MedicineBatch::firstOrCreate(
                    [
                        'facility_id'    => $facility->id,
                        'medication_id'  => $med->id,
                        'batch_number'   => $batchNumber,
                    ],
                    [
                        'supplier_id'       => $supplier->id,
                        'manufacture_date'  => now()->subMonths(2)->toDateString(),
                        'expiry_date'       => $expiry,
                        'purchase_price'    => 5.00,
                        'mrp'               => 12.00,
                        'selling_price'     => 10.00,
                        'tax_percent'       => $med->default_tax_percent ?? 12,
                        'received_quantity' => 200,
                        'current_quantity'  => 200,
                        'storage_location'  => $med->is_controlled ? 'Locked Cabinet' : 'Shelf A',
                    ],
                );

                if ($batch->wasRecentlyCreated) {
                    StockMovement::create([
                        'facility_id'       => $facility->id,
                        'medicine_batch_id' => $batch->id,
                        'medication_id'     => $med->id,
                        'performed_by'      => $systemUserId,
                        'movement_type'     => 'purchase',
                        'quantity'          => 200,
                        'balance_after'     => 200,
                        'source_type'       => Supplier::class,
                        'source_id'         => $supplier->id,
                        'reason'            => 'Opening stock from supplier',
                    ]);
                }
            }
        }

        // ------------------------------------------------------------------
        // 2. Patients — 30 realistic en_IN demographics
        // ------------------------------------------------------------------
        $uhidGen = app(UhidGenerator::class);
        $payers = ['self', 'self', 'self', 'insurance', 'corporate', 'government', 'charity'];
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

        $patients = collect();
        for ($i = 0; $i < 30; $i++) {
            $gender = $faker->randomElement(['male', 'male', 'female', 'female', 'other']);
            $first = $gender === 'male' ? $faker->firstNameMale : ($gender === 'female' ? $faker->firstNameFemale : $faker->firstName);
            $last  = $faker->lastName;
            $dob   = $faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d');
            $payer = $faker->randomElement($payers);

            $patient = Patient::create([
                'facility_id'   => $facility->id,
                'uhid'          => $uhidGen->generate($facility),
                'first_name'    => $first,
                'last_name'     => $last,
                'gender'        => $gender,
                'date_of_birth' => $dob,
                'blood_group'   => $faker->randomElement($bloodGroups),
                'marital_status'=> $faker->randomElement(['single', 'married', 'married', 'married', 'widowed']),
                'occupation'    => $faker->jobTitle,
                'phone'         => '+91 9' . $faker->numerify('#########'),
                'email'         => $faker->safeEmail,
                'address'       => [
                    'line1'   => $faker->streetAddress,
                    'city'    => $faker->city,
                    'state'   => 'Telangana',
                    'pincode' => $faker->postcode,
                    'country' => 'India',
                ],
                'emergency_contact' => [
                    'name'         => $faker->name,
                    'phone'        => '+91 9' . $faker->numerify('#########'),
                    'relationship' => $faker->randomElement(['Spouse', 'Parent', 'Child', 'Sibling']),
                ],
                'payer_type'         => $payer,
                'insurance_provider' => $payer === 'insurance' ? $faker->randomElement(['Star Health', 'HDFC ERGO', 'ICICI Lombard', 'New India']) : null,
                'insurance_number'   => $payer === 'insurance' ? strtoupper($faker->bothify('??######')) : null,
                'is_vip'             => $faker->boolean(5),
                'registered_at'      => now()->subDays(rand(1, 365)),
            ]);

            $patients->push($patient);
        }

        // ------------------------------------------------------------------
        // 3. Schedule templates + slots for next 14 days
        // ------------------------------------------------------------------
        $doctors = Provider::doctors()->get();
        $slotGen = app(SlotGenerator::class);

        foreach ($doctors as $doctor) {
            // Mon–Sat morning + evening.
            for ($dow = 1; $dow <= 6; $dow++) {
                ScheduleTemplate::firstOrCreate(
                    [
                        'provider_id' => $doctor->id,
                        'day_of_week' => $dow,
                        'start_time'  => '09:00:00',
                    ],
                    [
                        'facility_id'           => $facility->id,
                        'name'                  => 'Morning OPD',
                        'end_time'              => '13:00:00',
                        'slot_minutes'          => 15,
                        'max_patients_per_slot' => 1,
                        'is_active'             => true,
                    ]
                );
                ScheduleTemplate::firstOrCreate(
                    [
                        'provider_id' => $doctor->id,
                        'day_of_week' => $dow,
                        'start_time'  => '16:00:00',
                    ],
                    [
                        'facility_id'           => $facility->id,
                        'name'                  => 'Evening OPD',
                        'end_time'              => '19:00:00',
                        'slot_minutes'          => 15,
                        'max_patients_per_slot' => 1,
                        'is_active'             => true,
                    ]
                );
            }

            // Materialise slots: 7 days back (for past appts) → 14 days forward.
            $slotGen->generateForProvider(
                $doctor,
                CarbonImmutable::now()->subDays(7),
                CarbonImmutable::now()->addDays(14),
            );
        }

        if ($doctors->isEmpty()) {
            $this->command->warn('No doctors found — skipping appointment/encounter/billing seed.');
            return;
        }

        // ------------------------------------------------------------------
        // 4. Past appointments (completed with full clinical workflow)
        // ------------------------------------------------------------------
        $tokenGen = app(TokenGenerator::class);
        $defaultUser = \App\Models\Identity\User::where('email', 'reception@nishat.local')->value('id')
            ?? \App\Models\Identity\User::value('id');

        $completedAppointments = collect();
        $completedCount = 0;

        $pastSlots = Slot::with('provider')
            ->where('slot_date', '<', now()->toDateString())
            ->orderBy('slot_date')->orderBy('start_time')
            ->take(15)->get();

        foreach ($pastSlots as $slot) {
            $patient = $patients->random();
            $start = CarbonImmutable::parse($slot->slot_date->toDateString() . ' ' . $slot->start_time);

            $apptSeq = Appointment::count() + 1;
            $appt = Appointment::create([
                'appointment_number' => sprintf('APT-%s-%05d', now()->format('Ymd'), $apptSeq),
                'facility_id'        => $facility->id,
                'patient_id'         => $patient->id,
                'provider_id'        => $slot->provider_id,
                'slot_id'            => $slot->id,
                'scheduled_start'    => $start,
                'scheduled_end'      => $start->addMinutes(15),
                'status'             => 'completed',
                'token_number'       => sprintf('T%03d', $completedCount + 1),
                'booking_source'     => 'walk_in',
                'chief_complaint'    => $faker->randomElement([
                    'Fever and body aches for 3 days',
                    'Persistent cough with phlegm',
                    'Routine BP monitoring',
                    'Stomach pain after meals',
                    'Follow-up — diabetes',
                    'Headache and dizziness',
                ]),
                'created_by'         => $defaultUser,
                'fee_amount'         => $slot->provider->consultation_fee ?? 500,
                'checked_in_at'      => $start->subMinutes(15),
                'consult_started_at' => $start,
                'consult_ended_at'   => $start->addMinutes(20),
            ]);
            $slot->increment('booked_count');
            $appt->load('provider');
            $completedAppointments->push($appt);
            $completedCount++;
        }

        // ------------------------------------------------------------------
        // 5. For each completed appointment: encounter + vitals + Rx + maybe lab
        // ------------------------------------------------------------------
        $invoiceAgg = app(InvoiceAggregator::class);

        foreach ($completedAppointments as $appt) {
            $encNum = sprintf('ENC-%d-%06d', now()->year, $appt->id);

            $encounter = Encounter::create([
                'encounter_number' => $encNum,
                'facility_id'      => $facility->id,
                'patient_id'       => $appt->patient_id,
                'provider_id'      => $appt->provider_id,
                'appointment_id'   => $appt->id,
                'department_id'    => $appt->provider->department_id,
                'created_by'       => $appt->provider->user_id,
                'encounter_type'   => 'opd',
                'status'           => 'completed',
                'started_at'       => $appt->consult_started_at,
                'ended_at'         => $appt->consult_ended_at,
                'chief_complaint'  => $appt->chief_complaint,
                'history_of_present_illness' => $faker->paragraph(2),
                'examination_findings'       => $faker->paragraph(1),
                'assessment'                 => $faker->randomElement([
                    'Acute viral fever, likely self-limiting',
                    'Hypertension — well controlled',
                    'Type 2 diabetes — needs HbA1c follow-up',
                    'Acid peptic disease',
                    'Upper respiratory tract infection',
                ]),
                'plan'                       => 'Symptomatic treatment, follow-up in 7 days if no improvement',
                'advice_to_patient'          => 'Adequate hydration, rest, medication as prescribed',
                'clinical_summary'           => 'Stable patient. Plan documented. Rx issued.',
                'follow_up_date'             => $appt->scheduled_start->copy()->addDays(7)->toDateString(),
            ]);

            // Vitals — write 4-5 LOINC observations directly
            $vitals = [
                ['8480-6',  'Systolic blood pressure',  rand(110, 145), 'mmHg'],
                ['8462-4',  'Diastolic blood pressure', rand(70, 95),   'mmHg'],
                ['8867-4',  'Heart rate',                rand(65, 95),   'bpm'],
                ['8310-5',  'Body temperature',          round(36.5 + lcg_value(), 1), 'Cel'],
                ['59408-5', 'Oxygen saturation (SpO2)',  rand(95, 99),   '%'],
            ];
            foreach ($vitals as [$code, $name, $value, $unit]) {
                Observation::create([
                    'encounter_id'  => $encounter->id,
                    'patient_id'    => $appt->patient_id,
                    'recorded_by'   => $appt->provider->user_id,
                    'code'          => $code,
                    'code_system'   => 'loinc',
                    'display_name'  => $name,
                    'category'      => 'vital-signs',
                    'value_type'    => 'numeric',
                    'value_numeric' => $value,
                    'unit'          => $unit,
                    'effective_at'  => $encounter->started_at,
                ]);
            }

            // Prescription — 2-3 random meds
            $rxCount = Prescription::whereYear('created_at', now()->year)->count();
            $rx = Prescription::create([
                'prescription_number' => sprintf('RX-%d-%06d', now()->year, $rxCount + 1),
                'encounter_id'        => $encounter->id,
                'patient_id'          => $appt->patient_id,
                'provider_id'         => $appt->provider_id,
                'status'              => 'active',
                'prescribed_at'       => $appt->consult_ended_at,
                'notes'               => 'Take with water, after food where applicable.',
            ]);

            $randomMeds = $medications->random(rand(2, 3));
            foreach ($randomMeds as $med) {
                PrescriptionItem::create([
                    'prescription_id' => $rx->id,
                    'medication_id'   => $med->id,
                    'medication_text' => $med->displayName(),
                    'strength'        => $med->strength,
                    'dosage'          => '1 ' . ($med->form === 'syrup' ? 'tsp' : 'unit'),
                    'frequency'       => $faker->randomElement(['BD (1-0-1)', 'TDS (1-1-1)', 'OD (1-0-0)']),
                    'route'           => $med->form === 'iv_fluid' ? 'iv' : 'oral',
                    'duration_days'   => rand(3, 7),
                    'quantity'        => rand(5, 21),
                    'instructions'    => $faker->randomElement(['After food', 'Before food', 'At bedtime', 'With water']),
                    'status'          => 'pending',
                ]);
            }

            // Lab order for ~half of encounters
            if (rand(0, 1)) {
                $labTests = LabTest::inRandomOrder()->limit(rand(1, 3))->get();
                if ($labTests->isNotEmpty()) {
                    $labCount = LabOrder::whereYear('created_at', now()->year)->count();
                    $labOrder = LabOrder::create([
                        'lab_order_number' => sprintf('LAB-%d-%06d', now()->year, $labCount + 1),
                        'facility_id'      => $facility->id,
                        'patient_id'       => $appt->patient_id,
                        'encounter_id'     => $encounter->id,
                        'ordered_by'       => $appt->provider->user_id,
                        'priority'         => 'routine',
                        'status'           => 'completed',
                        'clinical_notes'   => $encounter->assessment,
                        'ordered_at'       => $encounter->started_at,
                        'completed_at'     => $encounter->started_at->copy()->addHours(6),
                    ]);

                    foreach ($labTests as $test) {
                        $orderTest = LabOrderTest::create([
                            'lab_order_id'   => $labOrder->id,
                            'lab_test_id'    => $test->id,
                            'test_name'      => $test->name,
                            'price_snapshot' => $test->price,
                            'status'         => 'completed',
                        ]);

                        // Generate a value within a sensible range
                        if ($test->reference_low !== null && $test->reference_high !== null) {
                            $low  = (float) $test->reference_low;
                            $high = (float) $test->reference_high;
                            $value = round($faker->randomFloat(2, $low * 0.8, $high * 1.1), 2);
                            $interp = $value < $low ? 'low' : ($value > $high ? 'high' : 'normal');
                            $abnormal = $interp !== 'normal';
                        } else {
                            $value = null;
                            $interp = 'normal';
                            $abnormal = false;
                        }

                        LabResult::create([
                            'lab_order_id'      => $labOrder->id,
                            'lab_test_id'       => $test->id,
                            'lab_order_test_id' => $orderTest->id,
                            'entered_by'        => \App\Models\Identity\User::where('email', 'lab@nishat.local')->value('id'),
                            'parameter_name'    => $test->name,
                            'value_numeric'     => $value,
                            'value_string'      => $value === null ? $test->reference_text : null,
                            'unit'              => $test->default_unit,
                            'reference_low'     => $test->reference_low,
                            'reference_high'    => $test->reference_high,
                            'reference_text'    => $test->reference_text,
                            'interpretation'    => $interp,
                            'is_abnormal'       => $abnormal,
                            'status'            => 'final',
                            'result_date'       => $encounter->started_at->copy()->addHours(6),
                            'verified_at'       => $encounter->started_at->copy()->addHours(7),
                            'verified_by'       => $appt->provider->user_id,
                        ]);
                    }
                }
            }

            // Generate the invoice (idempotent — aggregator firstOrCreate)
            $invoice = $invoiceAgg->createOrUpdateForEncounter($encounter);

            // Most invoices get a partial or full payment
            if ($faker->boolean(80) && $invoice->balance_amount > 0) {
                $payAmount = $faker->boolean(60)
                    ? (float) $invoice->balance_amount
                    : round((float) $invoice->balance_amount * 0.5, 2);

                $payCount = Payment::whereYear('created_at', now()->year)->count();
                Payment::create([
                    'payment_number'  => sprintf('PAY-%s-%06d', now()->format('Ym'), $payCount + 1),
                    'facility_id'     => $facility->id,
                    'invoice_id'      => $invoice->id,
                    'patient_id'      => $invoice->patient_id,
                    'received_by'     => \App\Models\Identity\User::where('email', 'cashier@nishat.local')->value('id') ?? $defaultUser,
                    'payment_method'  => $faker->randomElement(['cash', 'upi', 'card']),
                    'amount'          => $payAmount,
                    'status'          => 'completed',
                    'paid_at'         => $appt->consult_ended_at->copy()->addMinutes(30),
                ]);
                $invoiceAgg->recalculateTotals($invoice);
            }
        }

        // ------------------------------------------------------------------
        // 6. Upcoming appointments (booked / confirmed / checked-in)
        // ------------------------------------------------------------------
        $upcomingSlots = Slot::with('provider')
            ->where('slot_date', '>=', now()->toDateString())
            ->whereColumn('booked_count', '<', 'capacity')
            ->orderBy('slot_date')->orderBy('start_time')
            ->take(15)->get();

        foreach ($upcomingSlots as $slot) {
            $patient = $patients->random();
            $start = CarbonImmutable::parse($slot->slot_date->toDateString() . ' ' . $slot->start_time);
            $statusRoll = rand(1, 10);
            $status = match (true) {
                $statusRoll <= 6 => 'booked',
                $statusRoll <= 8 => 'confirmed',
                default          => 'checked_in',
            };

            $apptSeq = Appointment::count() + 1;
            $appt = Appointment::create([
                'appointment_number' => sprintf('APT-%s-%05d', now()->format('Ymd'), $apptSeq),
                'facility_id'        => $facility->id,
                'patient_id'         => $patient->id,
                'provider_id'        => $slot->provider_id,
                'slot_id'            => $slot->id,
                'scheduled_start'    => $start,
                'scheduled_end'      => $start->addMinutes(15),
                'status'             => $status,
                'token_number'       => $tokenGen->next($slot->provider_id, $slot->slot_date->toDateString()),
                'booking_source'     => $faker->randomElement(['walk_in', 'phone', 'public_website']),
                'chief_complaint'    => $faker->randomElement([
                    'Annual health check-up',
                    'Persistent cough',
                    'Joint pain',
                    'Routine follow-up',
                ]),
                'created_by'         => $defaultUser,
                'fee_amount'         => $slot->provider->consultation_fee ?? 500,
                'checked_in_at'      => $status === 'checked_in' ? now() : null,
            ]);
            $slot->increment('booked_count');
        }

        // ------------------------------------------------------------------
        // 7. IPD admissions — 1 active + 2 discharged
        // ------------------------------------------------------------------
        $availableBeds = Bed::with('room')
            ->where('status', Bed::STATUS_AVAILABLE)
            ->take(3)->get();
        if ($availableBeds->count() >= 3) {
            $patient1 = $patients[0];
            $this->createAdmission($facility, $patient1, $doctors->first(), $availableBeds[0],
                'Active', null, $defaultUser, $faker, $invoiceAgg);

            $patient2 = $patients[1];
            $this->createAdmission($facility, $patient2, $doctors->first(), $availableBeds[1],
                'Discharged routine', now()->subDays(2), $defaultUser, $faker, $invoiceAgg);

            $patient3 = $patients[2];
            $this->createAdmission($facility, $patient3, $doctors->first(), $availableBeds[2],
                'Discharged routine', now()->subDays(5), $defaultUser, $faker, $invoiceAgg);
        }

        $this->command->info(sprintf(
            'Demo data ready: %d patients, %d completed appointments, %d encounters, %d invoices.',
            $patients->count(),
            $completedAppointments->count(),
            Encounter::count(),
            Invoice::count(),
        ));
    }

    private function createAdmission(
        Facility $facility, Patient $patient, Provider $doctor, Bed $bed,
        string $tag, $dischargedAt, int $userId, $faker, InvoiceAggregator $invoiceAgg,
    ): void {
        $admittedAt = $dischargedAt ? $dischargedAt->copy()->subDays(rand(2, 4)) : now()->subDays(rand(0, 2));
        $admissionCount = Admission::whereYear('created_at', now()->year)->count();

        $admission = Admission::create([
            'admission_number'     => sprintf('ADM-%d-%06d', now()->year, $admissionCount + 1),
            'facility_id'          => $facility->id,
            'patient_id'           => $patient->id,
            'admitting_doctor_id'  => $doctor->id,
            'department_id'        => $doctor->department_id,
            'current_bed_id'       => $dischargedAt ? null : $bed->id,
            'admission_type'       => $faker->randomElement(['planned', 'emergency']),
            'status'               => $dischargedAt ? Admission::STATUS_DISCHARGED : Admission::STATUS_ACTIVE,
            'admitted_at'          => $admittedAt,
            'discharged_at'        => $dischargedAt,
            'admission_reason'     => $faker->randomElement([
                'Acute appendicitis — surgical management',
                'Pneumonia — respiratory support',
                'Cardiac evaluation — chest pain',
                'Gastroenteritis with dehydration',
            ]),
            'provisional_diagnosis'=> 'TBD',
            'admitted_by'          => $userId,
            'discharge_type'       => $dischargedAt ? 'routine' : null,
            'discharged_by'        => $dischargedAt ? $userId : null,
        ]);

        $assignment = BedAssignment::create([
            'admission_id'        => $admission->id,
            'bed_id'              => $bed->id,
            'patient_id'          => $patient->id,
            'assigned_by'         => $userId,
            'assigned_at'         => $admittedAt,
            'vacated_at'          => $dischargedAt,
            'daily_rate_snapshot' => $bed->room?->daily_rate ?? 1000,
        ]);

        BedStatusHistory::create([
            'bed_id'          => $bed->id,
            'patient_id'      => $patient->id,
            'admission_id'    => $admission->id,
            'changed_by'      => $userId,
            'status'          => $dischargedAt ? Bed::STATUS_AVAILABLE : Bed::STATUS_OCCUPIED,
            'previous_status' => Bed::STATUS_AVAILABLE,
            'start_at'        => $admittedAt,
            'end_at'          => $dischargedAt,
        ]);

        $bed->update(['status' => $dischargedAt ? Bed::STATUS_AVAILABLE : Bed::STATUS_OCCUPIED]);

        if ($dischargedAt) {
            DischargeSummary::create([
                'admission_id'           => $admission->id,
                'prepared_by'            => $doctor->user_id,
                'discharge_diagnosis'    => $admission->admission_reason,
                'condition_at_discharge' => 'Stable, ambulatory',
                'course_in_hospital'     => 'Uneventful recovery, responded well to treatment',
                'treatment_given'        => 'IV antibiotics, analgesics, supportive care',
                'follow_up_advice'       => 'Review in 7 days with reports',
                'follow_up_date'         => $dischargedAt->copy()->addDays(7)->toDateString(),
                'is_finalized'           => true,
                'finalized_at'           => $dischargedAt,
            ]);
        }

        $invoice = $invoiceAgg->createOrUpdateForAdmission($admission);
        if ($invoice && $invoice->balance_amount > 0 && $dischargedAt) {
            $payCount = Payment::whereYear('created_at', now()->year)->count();
            Payment::create([
                'payment_number'  => sprintf('PAY-%s-%06d', now()->format('Ym'), $payCount + 1),
                'facility_id'     => $facility->id,
                'invoice_id'      => $invoice->id,
                'patient_id'      => $patient->id,
                'received_by'     => \App\Models\Identity\User::where('email', 'cashier@nishat.local')->value('id') ?? $userId,
                'payment_method'  => 'card',
                'amount'          => (float) $invoice->balance_amount,
                'status'          => 'completed',
                'paid_at'         => $dischargedAt,
            ]);
            $invoiceAgg->recalculateTotals($invoice);
        }
    }
}
