<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 7 of 8: BILLING & FINANCE
 *
 * Unified invoicing — every billable event (consultation, bed charge,
 * pharmacy dispense, lab order, procedure) is source-linked on the invoice
 * via polymorphic "source" columns (SRS §4.9 Business Rules).
 *
 * SRS reference: §4.9 Billing & Finance
 */
return new class extends Migration {
    public function up(): void
    {
        // Charge catalog — consultations, procedures, packages, admin fees
        Schema::create('charge_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('category', [
                'consultation', 'procedure', 'room_charge', 'nursing_charge',
                'emergency', 'ambulance', 'package', 'registration', 'other'
            ]);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->string('hsn_code', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'category', 'is_active']);
        });

        // Packages — bundled services at a package price
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('charge_catalog')->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained('charge_catalog')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        // Payer types master — cash, card, insurance, corporate, government, charity
        Schema::create('payer_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('type', ['cash', 'insurance', 'corporate', 'government', 'charity']);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'code']);
        });

        // -------------------------------------------------------------------
        // Invoices
        // -------------------------------------------------------------------
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();       // "INV-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('invoice_type', ['opd', 'ipd', 'pharmacy', 'laboratory', 'mixed'])->default('opd');
            $table->enum('status', ['draft', 'pending', 'partial', 'paid', 'cancelled', 'refunded'])->default('pending');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0); // computed: total - paid + refunded
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status', 'invoice_date']);
            $table->index(['patient_id', 'invoice_date']);
            $table->index('status');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Polymorphic source — EVERY item is source-linked (SRS Business Rule)
            $table->nullableMorphs('source');                     // source_type, source_id
            $table->string('item_code', 50)->nullable();
            $table->string('item_name');
            $table->string('category', 50)->nullable();           // "consultation", "bed", "pharmacy", "lab"
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'category']);
        });

        // -------------------------------------------------------------------
        // Payments
        // -------------------------------------------------------------------
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();       // "PAY-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('received_by')->constrained('users'); // cashier
            $table->enum('payment_method', ['cash', 'card', 'upi', 'netbanking', 'cheque', 'insurance', 'wallet', 'other'])->default('cash');
            $table->decimal('amount', 12, 2);
            $table->string('reference_number')->nullable();       // txn ID / cheque no
            $table->string('gateway', 50)->nullable();            // stripe / razorpay / manual
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'disputed'])->default('completed');
            $table->text('notes')->nullable();
            $table->foreignId('cashier_shift_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'paid_at']);
            $table->index(['patient_id', 'paid_at']);
            $table->index('payment_method');
        });

        // Refunds — tracked separately for clean audit
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 30)->unique();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'completed'])->default('pending_approval');
            $table->timestamp('processed_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status']);
        });

        // Discounts requiring approval (SRS §4.9 Business Rule)
        Schema::create('discount_approvals', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('target');                     // invoice or invoice_item
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('discount_percent', 5, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        // Cashier shift management (SRS §4.9)
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('cashier_id')->constrained('users');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('expected_closing_balance', 12, 2)->nullable();
            $table->decimal('actual_closing_balance', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed', 'reconciled'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cashier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('discount_approvals');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payer_types');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('charge_catalog');
    }
};
