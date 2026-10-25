<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 6 of 8: LABORATORY & DIAGNOSTICS
 *
 * Test catalog with reference ranges, order placement, sample tracking,
 * result entry (with abnormal flagging), and PDF uploads for imaging.
 *
 * SRS reference: §4.8 Laboratory & Diagnostics, §8.5 Lab Flow
 */
return new class extends Migration {
    public function up(): void
    {
        // Test catalog — master list of tests the lab offers
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 30);                           // "CBC", "LFT", "HBA1C"
            $table->string('name');
            $table->string('category', 50)->nullable();           // "Hematology", "Biochemistry", "Radiology"
            $table->enum('test_type', ['single', 'panel', 'radiology'])->default('single');
            $table->string('specimen_type', 50)->nullable();       // "Blood (EDTA)", "Urine", "Serum"
            $table->string('default_unit', 20)->nullable();
            $table->decimal('reference_low', 15, 4)->nullable();
            $table->decimal('reference_high', 15, 4)->nullable();
            $table->string('reference_text', 255)->nullable();    // for non-numeric tests
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('turnaround_hours')->nullable();      // expected TAT
            $table->text('preparation_instructions')->nullable(); // fasting required, etc.
            $table->boolean('is_outsourced')->default(false);
            $table->string('outsource_partner')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'category', 'is_active']);
        });

        // For panel tests — which components they include
        Schema::create('lab_test_panel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->foreignId('component_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['panel_test_id', 'component_test_id']);
        });

        // Lab orders — specialization of generic orders (clinical domain).
        // When a doctor orders a lab test, a row is created here alongside
        // the generic `orders` row. This denormalization is intentional —
        // it dramatically simplifies lab queries without losing the FHIR mapping.
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('lab_order_number', 30)->unique();     // "LAB-2026-000001"
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->constrained('users'); // doctor
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');
            $table->enum('status', [
                'ordered', 'sample_collection_pending', 'sample_collected',
                'in_process', 'result_pending_review', 'completed', 'cancelled'
            ])->default('ordered');
            $table->text('clinical_notes')->nullable();           // why ordered, relevant history
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status', 'ordered_at']);
            $table->index(['patient_id', 'ordered_at']);
        });

        Schema::create('lab_order_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained();
            $table->string('test_name');                          // denormalized
            $table->decimal('price_snapshot', 10, 2);
            $table->enum('status', ['pending', 'sample_collected', 'in_process', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // Sample tracking
        Schema::create('lab_samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_number', 30)->unique();        // barcode-ready
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('specimen_type', 50);
            $table->string('container_type', 50)->nullable();
            $table->enum('status', ['pending_collection', 'collected', 'in_transit', 'received', 'processed', 'rejected'])->default('pending_collection');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lab_order_id', 'status']);
        });

        // Results — one row per test component
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained();
            $table->foreignId('lab_order_test_id')->nullable()->constrained('lab_order_tests')->nullOnDelete();
            $table->foreignId('lab_sample_id')->nullable()->constrained('lab_samples')->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('parameter_name');                     // "Hemoglobin"
            $table->string('value_string', 500)->nullable();
            $table->decimal('value_numeric', 15, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('reference_low', 15, 4)->nullable();
            $table->decimal('reference_high', 15, 4)->nullable();
            $table->string('reference_text', 255)->nullable();
            $table->enum('interpretation', ['normal', 'low', 'high', 'critical_low', 'critical_high', 'abnormal'])->nullable();
            $table->boolean('is_abnormal')->default(false);       // denormalized for fast dashboards
            $table->enum('status', ['pending', 'preliminary', 'final', 'amended', 'cancelled'])->default('final');
            $table->text('technician_notes')->nullable();
            $table->timestamp('result_date')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lab_order_id', 'status']);
            $table->index('is_abnormal');
        });

        // Uploaded result files (PDF reports, images for radiology)
        Schema::create('lab_result_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_result_files');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_samples');
        Schema::dropIfExists('lab_order_tests');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_test_panel_items');
        Schema::dropIfExists('lab_tests');
    }
};
