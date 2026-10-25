<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 4 of 8: ADMISSION & BEDS
 *
 * Hierarchical bed management (Building → Floor → Ward → Room → Bed) plus
 * admission/transfer/discharge workflow. SRS treats IPD as first-class;
 * this schema reflects that.
 *
 * SRS reference: §4.4 IPD Workflow, §4.10 Ward/Room/Bed Management
 */
return new class extends Migration {
    public function up(): void
    {
        // Buildings and floors are simple strings on rooms for MVP; the
        // hierarchy is expressible but we don't over-engineer it.
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 30);
            $table->string('name');                               // "ICU", "General Ward A"
            $table->string('building', 50)->nullable();
            $table->string('floor', 20)->nullable();
            $table->enum('category', ['general', 'semi_private', 'private', 'icu', 'emergency', 'maternity', 'pediatric', 'other'])->default('general');
            $table->string('gender_restriction', 20)->nullable(); // male / female / null (mixed)
            $table->integer('total_beds')->default(0);            // denormalized counter
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'code']);
            $table->index('category');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');                               // "Room 101"
            $table->integer('capacity')->default(1);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->json('amenities')->nullable();                // ["AC", "TV", "attached_bathroom"]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ward_id', 'code']);
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('ward_id')->constrained();
            $table->foreignId('room_id')->constrained();
            $table->string('bed_number', 20);                     // "101-A"
            $table->enum('status', [
                'available', 'occupied', 'reserved', 'cleaning', 'maintenance', 'blocked'
            ])->default('available');
            $table->decimal('daily_rate_override', 10, 2)->nullable(); // if differs from room rate
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['room_id', 'bed_number']);
            $table->index(['facility_id', 'status']);
        });

        // Full status history for every bed — answers "who was in bed 12 last Tuesday?"
        Schema::create('bed_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admission_id')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30);
            $table->string('previous_status', 30)->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();              // null = current
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['bed_id', 'start_at']);
            $table->index(['patient_id', 'start_at']);
        });

        // -------------------------------------------------------------------
        // Admissions — IPD stays
        // A single admission can have multiple bed assignments (transfers).
        // -------------------------------------------------------------------
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number', 30)->unique();     // "ADM-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('admitting_doctor_id')->constrained('providers');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->enum('admission_type', ['planned', 'emergency', 'transfer', 'day_care', 'observation'])->default('planned');
            $table->enum('status', ['pending', 'active', 'discharged', 'absconded', 'transferred_out', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('admitted_at')->useCurrent();
            $table->timestamp('discharged_at')->nullable();
            $table->text('admission_reason');
            $table->text('provisional_diagnosis')->nullable();
            $table->foreignId('admitted_by')->constrained('users'); // reception staff
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('discharge_type', ['routine', 'against_medical_advice', 'referral', 'absconded', 'death', 'transfer'])->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status']);
            $table->index(['patient_id', 'admitted_at']);
        });

        // Individual bed assignments within an admission (transfers)
        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('assigned_at');
            $table->timestamp('vacated_at')->nullable();
            $table->text('transfer_reason')->nullable();
            $table->decimal('daily_rate_snapshot', 10, 2);        // price at time of assignment
            $table->timestamps();

            $table->index(['admission_id', 'assigned_at']);
            $table->index(['bed_id', 'assigned_at']);
        });

        // Discharge summary — generated at discharge, printable
        Schema::create('discharge_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('prepared_by')->constrained('users');
            $table->text('presenting_complaints')->nullable();
            $table->text('history')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('investigations_summary')->nullable();
            $table->text('treatment_given')->nullable();
            $table->text('course_in_hospital')->nullable();
            $table->text('condition_at_discharge')->nullable();
            $table->text('discharge_diagnosis');
            $table->text('discharge_medications')->nullable();
            $table->text('follow_up_advice')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('diet_advice')->nullable();
            $table->text('activity_restrictions')->nullable();
            $table->boolean('is_finalized')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discharge_summaries');
        Schema::dropIfExists('bed_assignments');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('bed_status_history');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('wards');
    }
};
