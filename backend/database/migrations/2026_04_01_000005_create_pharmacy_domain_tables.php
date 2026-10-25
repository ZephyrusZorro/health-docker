<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 5 of 8: PHARMACY
 *
 * Batch-level inventory, FEFO (First Expiry First Out) dispensing, supplier
 * management, purchase orders, and POS billing. Every stock movement is
 * auditable (SRS §4.7 Business Rules).
 *
 * SRS reference: §4.7 Pharmacy System, §8.4 Pharmacy Flow
 */
return new class extends Migration {
    public function up(): void
    {
        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 30);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->string('gstin', 30)->nullable();              // India GST; generalize as tax_id later
            $table->string('license_number', 50)->nullable();
            $table->integer('payment_terms_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'code']);
        });

        // Medicine batches — this is where pharmacy inventory actually LIVES.
        // medications (clinical domain) is the catalog; batches are the stock.
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 50);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date');
            $table->decimal('purchase_price', 10, 2);             // per unit
            $table->decimal('mrp', 10, 2);                         // max retail price
            $table->decimal('selling_price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->integer('received_quantity');
            $table->integer('current_quantity');                   // decremented on dispense
            $table->string('storage_location')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['medication_id', 'batch_number', 'facility_id']);
            $table->index(['facility_id', 'expiry_date']);         // for FEFO selection
            $table->index(['medication_id', 'current_quantity']);
        });

        // Unified stock ledger — every in/out movement (SRS Business Rule)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('medicine_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained();
            $table->foreignId('performed_by')->constrained('users');
            $table->enum('movement_type', [
                'purchase', 'dispense', 'return_in', 'return_out',
                'adjustment_in', 'adjustment_out', 'expiry_writeoff', 'transfer_in', 'transfer_out'
            ]);
            $table->integer('quantity');                           // always positive; direction from type
            $table->integer('balance_after');                      // snapshot for audit
            $table->nullableMorphs('source');                      // purchase_order / dispense / etc.
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['medicine_batch_id', 'created_at']);
            $table->index(['facility_id', 'movement_type', 'created_at']);
        });

        // Purchase orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();            // "PO-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('status', ['draft', 'sent', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status', 'order_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained();
            $table->integer('quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        // Goods receipt — ties PO to actual batches received
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number', 30)->unique();           // Goods Receipt Note
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->date('received_date');
            $table->string('invoice_number')->nullable();         // supplier's invoice
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained(); // batch created at receipt
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        // -------------------------------------------------------------------
        // Dispenses — execution of a prescription (or OTC POS sale)
        // -------------------------------------------------------------------
        Schema::create('dispenses', function (Blueprint $table) {
            $table->id();
            $table->string('dispense_number', 30)->unique();      // "DSP-2026-000001"
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete(); // null for OTC walk-in
            $table->foreignId('pharmacist_id')->constrained('users');
            $table->enum('dispense_type', ['prescription', 'otc', 'in_patient', 'emergency'])->default('prescription');
            $table->enum('status', ['draft', 'completed', 'cancelled', 'partially_returned', 'returned'])->default('completed');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('dispensed_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'dispensed_at']);
            $table->index(['patient_id', 'dispensed_at']);
        });

        Schema::create('dispense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_batch_id')->constrained();
            $table->foreignId('medication_id')->constrained();
            $table->string('medication_name');                    // denormalized
            $table->string('batch_number', 50);                   // denormalized
            $table->date('expiry_date');                          // denormalized
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->boolean('is_substituted')->default(false);
            $table->timestamps();

            $table->index('medicine_batch_id');
        });

        // Returns
        Schema::create('dispense_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 30)->unique();
            $table->foreignId('dispense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('processed_by')->constrained('users');
            $table->text('reason');
            $table->decimal('refund_amount', 12, 2);
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('dispense_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispense_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispense_item_id')->constrained();
            $table->integer('quantity');
            $table->decimal('refund_amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispense_return_items');
        Schema::dropIfExists('dispense_returns');
        Schema::dropIfExists('dispense_items');
        Schema::dropIfExists('dispenses');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('suppliers');
    }
};
