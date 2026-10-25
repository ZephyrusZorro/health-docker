<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 2 of 8: SCHEDULING
 *
 * Covers: weekly schedule templates per provider, materialised slots,
 * appointments (the actual reservation), and the queue/token system.
 *
 * SRS reference: §4.2 Appointment & Scheduling.
 */
return new class extends Migration {
    public function up(): void
    {
        // -------------------------------------------------------------------
        // Visit type master — "New Consultation", "Follow-up", "Emergency"
        // -------------------------------------------------------------------
        Schema::create('visit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 30);
            $table->string('name');
            $table->integer('default_duration_minutes')->default(15);
            $table->decimal('default_fee', 10, 2)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('allow_online_booking')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['facility_id', 'code']);
        });

        // -------------------------------------------------------------------
        // Schedule templates — weekly patterns per provider
        // -------------------------------------------------------------------
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->default('Default');
            $table->tinyInteger('day_of_week');                  // 1=Mon..7=Sun (ISO)
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_minutes')->default(15);
            $table->integer('buffer_minutes')->default(0);
            $table->integer('max_patients_per_slot')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id', 'day_of_week', 'is_active']);
        });

        // -------------------------------------------------------------------
        // Materialised slots — generated from templates for the rolling window
        // -------------------------------------------------------------------
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->constrained();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('capacity')->default(1);
            $table->integer('booked_count')->default(0);
            $table->enum('status', ['available', 'full', 'blocked', 'cancelled'])->default('available');
            $table->string('blocked_reason')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'slot_date', 'start_time']);
            $table->index(['provider_id', 'slot_date', 'status']);
        });

        // Doctor time-off / holidays — overrides slots
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('exception_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->enum('type', ['leave', 'holiday', 'conference', 'emergency', 'other'])->default('leave');
            $table->timestamps();

            $table->index(['provider_id', 'exception_date']);
        });

        // -------------------------------------------------------------------
        // Appointments — the patient's commitment to a specific slot
        // Lifecycle (SRS §4.2): booked → checked_in → in_consultation → completed
        // -------------------------------------------------------------------
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_number', 30)->unique();   // "APT-20260424-0001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('provider_id')->constrained();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->enum('status', [
                'booked', 'confirmed', 'checked_in', 'in_consultation',
                'completed', 'cancelled', 'no_show', 'rescheduled'
            ])->default('booked');
            $table->string('token_number', 20)->nullable();
            $table->enum('booking_source', ['walk_in', 'phone', 'public_website', 'chatbot', 'portal', 'referral'])->default('walk_in');
            $table->text('chief_complaint')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('consult_started_at')->nullable();
            $table->timestamp('consult_ended_at')->nullable();
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->boolean('advance_fee_paid')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->json('reminder_log')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'scheduled_start', 'status']);
            $table->index(['patient_id', 'scheduled_start']);
            $table->index(['provider_id', 'scheduled_start']);
            $table->index('status');
        });

        // -------------------------------------------------------------------
        // Queue / token state — one row per (provider, date)
        // -------------------------------------------------------------------
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('queue_date');
            $table->integer('current_token')->default(0);          // last-served
            $table->integer('next_token')->default(1);             // next to issue
            $table->integer('total_waiting')->default(0);
            $table->integer('total_seen')->default(0);
            $table->timestamps();

            $table->unique(['provider_id', 'queue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('slots');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('visit_types');
    }
};
