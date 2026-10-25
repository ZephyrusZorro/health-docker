<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 3 of 8: CLINICAL (EMR)
 *
 * This is the clinical core of the platform (SRS §4.6). The schema is
 * deliberately shaped to map cleanly to FHIR R4 resources so the export
 * adapter in Phase 3 is a thin projection layer rather than a rewrite.
 *
 *   encounters         → FHIR Encounter
 *   observations       → FHIR Observation (includes vitals)
 *   conditions         → FHIR Condition (diagnoses)
 *   allergies          → FHIR AllergyIntolerance
 *   medications        → FHIR Medication
 *   prescriptions      → FHIR MedicationRequest
 *   orders             → FHIR ServiceRequest
 *   clinical_notes     → FHIR DocumentReference / Composition
 *   care_plans         → FHIR CarePlan
 *
 * SRS reference: §4.6 EMR Patient Chart, §5.6 Compliance & Interoperability
 */
return new class extends Migration {
    public function up(): void
    {
        // -------------------------------------------------------------------
        // Encounters — OPD visits, IPD stays, ER visits, telemedicine calls
        // One row per clinical "interaction"; everything else hangs off this.
        // -------------------------------------------------------------------
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->string('encounter_number', 30)->unique();     // "ENC-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('encounter_type', ['opd', 'ipd', 'emergency', 'telemedicine', 'home_visit'])->default('opd');
            $table->enum('status', ['in_progress', 'on_hold', 'completed', 'cancelled'])->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('history_of_present_illness')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('advice_to_patient')->nullable();
            $table->text('clinical_summary')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->json('metadata')->nullable();                 // flexible bucket for future fields
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'started_at']);
            $table->index(['provider_id', 'started_at']);
            $table->index(['facility_id', 'encounter_type', 'status']);
        });

        // -------------------------------------------------------------------
        // Observations — vitals, measurements, lab values, scored forms.
        // Unified FHIR-style Observation table with a type discriminator.
        // -------------------------------------------------------------------
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('code', 50);                           // "vital.blood_pressure_systolic", "lab.hemoglobin"
            $table->string('code_system', 50)->default('internal'); // internal | loinc | snomed
            $table->string('display_name');                       // "Systolic Blood Pressure"
            $table->string('category', 30);                       // vital-signs, laboratory, social-history, exam
            $table->enum('value_type', ['numeric', 'string', 'boolean', 'coded', 'range']);
            $table->decimal('value_numeric', 15, 4)->nullable();
            $table->string('value_string', 500)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('unit', 30)->nullable();               // mmHg, bpm, mg/dL, kg, cm, %, F, C
            $table->decimal('reference_low', 15, 4)->nullable();
            $table->decimal('reference_high', 15, 4)->nullable();
            $table->enum('interpretation', ['normal', 'low', 'high', 'critical_low', 'critical_high', 'abnormal'])->nullable();
            $table->timestamp('effective_at');                    // when the observation was actually taken
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'code', 'effective_at']);
            $table->index(['encounter_id', 'category']);
            $table->index('effective_at');
        });

        // -------------------------------------------------------------------
        // Conditions / Diagnoses — FHIR Condition (ICD-10-ready)
        // -------------------------------------------------------------------
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('code', 30)->nullable();               // ICD-10 code, e.g. "E11.9"
            $table->string('code_system', 30)->default('icd10');
            $table->string('display_name');                       // "Type 2 diabetes mellitus"
            $table->enum('clinical_status', ['active', 'recurrence', 'relapse', 'inactive', 'remission', 'resolved'])->default('active');
            $table->enum('verification_status', ['unconfirmed', 'provisional', 'differential', 'confirmed', 'refuted', 'entered_in_error'])->default('confirmed');
            $table->enum('severity', ['mild', 'moderate', 'severe'])->nullable();
            $table->enum('category', ['problem_list_item', 'encounter_diagnosis', 'chronic', 'acute'])->default('encounter_diagnosis');
            $table->date('onset_date')->nullable();
            $table->date('abatement_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'clinical_status']);
            $table->index('code');
        });

        // -------------------------------------------------------------------
        // Allergies — FHIR AllergyIntolerance
        // -------------------------------------------------------------------
        Schema::create('allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('recorded_by')->constrained('users');
            $table->enum('type', ['allergy', 'intolerance']);
            $table->enum('category', ['drug', 'food', 'environment', 'biologic'])->default('drug');
            $table->enum('criticality', ['low', 'high', 'unable_to_assess'])->default('low');
            $table->string('substance');                          // "Penicillin", "Peanuts"
            $table->string('substance_code', 50)->nullable();
            $table->string('reaction')->nullable();               // "rash", "anaphylaxis"
            $table->enum('severity', ['mild', 'moderate', 'severe', 'life_threatening'])->nullable();
            $table->enum('status', ['active', 'inactive', 'resolved'])->default('active');
            $table->date('onset_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        // -------------------------------------------------------------------
        // Medications — master catalogue used by both pharmacy and prescriptions.
        // Pharmacy batches (medicine_batches) reference this table.
        // -------------------------------------------------------------------
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                 // internal SKU
            $table->string('generic_name');                       // "Paracetamol"
            $table->string('brand_name')->nullable();             // "Crocin"
            $table->string('strength', 50)->nullable();           // "500mg", "5mg/ml"
            $table->enum('form', ['tablet', 'capsule', 'syrup', 'injection', 'ointment', 'drops', 'inhaler', 'suppository', 'other'])->default('tablet');
            $table->string('category', 50)->nullable();           // "Analgesic", "Antibiotic"
            $table->string('manufacturer')->nullable();
            $table->string('hsn_code', 20)->nullable();           // tax code
            $table->decimal('default_tax_percent', 5, 2)->default(0);
            $table->boolean('is_prescription_required')->default(true);
            $table->boolean('is_controlled')->default(false);     // narcotics / scheduled drugs
            $table->integer('reorder_level')->default(10);        // alert when total stock ≤ this
            $table->text('storage_instructions')->nullable();
            $table->text('common_side_effects')->nullable();
            $table->json('contraindications')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('generic_name');
            $table->index('brand_name');
            $table->index('is_active');
        });

        // -------------------------------------------------------------------
        // Prescriptions — FHIR MedicationRequest
        // Written by doctor; consumed by pharmacy (dispense).
        // -------------------------------------------------------------------
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number', 30)->unique();  // "RX-2026-000001"
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('provider_id')->constrained();
            $table->enum('status', ['draft', 'active', 'partially_dispensed', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('prescribed_at')->useCurrent();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'prescribed_at']);
            $table->index(['provider_id', 'prescribed_at']);
            $table->index('status');
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medication_text');                    // denormalized for history
            $table->string('strength', 50)->nullable();
            $table->string('dosage', 50);                         // "1 tablet"
            $table->string('frequency', 50);                      // "TID" or "1-0-1"
            $table->string('route', 30)->default('oral');         // oral, IV, IM, topical, etc.
            $table->integer('duration_days')->nullable();
            $table->integer('quantity')->nullable();              // total units to dispense
            $table->string('timing_relation', 30)->nullable();    // before_food / after_food / with_food
            $table->text('instructions')->nullable();
            $table->boolean('is_substitution_allowed')->default(true);
            $table->integer('dispensed_quantity')->default(0);
            $table->enum('status', ['pending', 'partially_dispensed', 'dispensed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // -------------------------------------------------------------------
        // Orders — FHIR ServiceRequest (lab, imaging, procedure)
        // Lab orders are rendered from this table; same for radiology.
        // -------------------------------------------------------------------
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();         // "ORD-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('ordered_by')->constrained('users');
            $table->enum('order_type', ['lab', 'imaging', 'procedure', 'referral']);
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');
            $table->enum('status', ['draft', 'active', 'in_progress', 'completed', 'cancelled', 'entered_in_error'])->default('active');
            $table->text('reason')->nullable();                   // clinical indication
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'order_type', 'status']);
            $table->index(['facility_id', 'status', 'ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('item');                       // polymorphic to lab_tests / charge_catalog
            $table->string('item_name');                          // denormalized
            $table->string('item_code', 50)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->enum('status', ['pending', 'collected', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // -------------------------------------------------------------------
        // Clinical notes — free text + structured forms (SRS §4.6)
        // -------------------------------------------------------------------
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->enum('note_type', [
                'consultation', 'progress', 'nursing', 'discharge',
                'operative', 'referral', 'follow_up', 'other'
            ])->default('consultation');
            $table->string('title')->nullable();
            $table->longText('content');                          // plain text or markdown
            $table->json('structured_content')->nullable();       // form responses if applicable
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'note_type', 'created_at']);
            $table->index(['encounter_id', 'note_type']);
        });

        // Attachments (scans, uploaded reports) tied to encounter or patient
        Schema::create('clinical_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('title');
            $table->string('category', 50);                       // "xray", "mri", "report", "ecg"
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'category']);
        });

        // Care plans — active treatment goals (SRS §4.6)
        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'on_hold', 'completed', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('goals')->nullable();                    // [{description, target_date, status}]
            $table->json('activities')->nullable();               // [{description, frequency, status}]
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        // Forms master (customizable clinical forms, SRS §4.6)
        Schema::create('clinical_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->json('schema');                               // JSON Schema for the form
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_forms');
        Schema::dropIfExists('care_plans');
        Schema::dropIfExists('clinical_attachments');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medications');
        Schema::dropIfExists('allergies');
        Schema::dropIfExists('conditions');
        Schema::dropIfExists('observations');
        Schema::dropIfExists('encounters');
    }
};
