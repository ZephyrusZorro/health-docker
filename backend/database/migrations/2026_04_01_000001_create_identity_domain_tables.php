<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 1 of 8: IDENTITY & PEOPLE
 *
 * Covers: facilities (multi-branch ready), users, staff, providers (doctors),
 * departments, and the shared "people" foundation referenced by patients,
 * staff, and users.
 *
 * SRS reference: §7 Database Domain Model — Identity & People
 */
return new class extends Migration {
    public function up(): void
    {
        // -------------------------------------------------------------------
        // Facilities — tenant-aware from day one (SRS §5.3, §15.2)
        // -------------------------------------------------------------------
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();              // e.g. "NH-MAIN"
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();               // JSONB: line1, line2, city, state, pincode, country
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('default_currency', 3)->default('INR');
            $table->json('settings')->nullable();              // per-facility overrides
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------------------
        // Departments (Cardiology, General Medicine, etc.)
        // -------------------------------------------------------------------
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'code']);
        });

        // -------------------------------------------------------------------
        // Users — authentication subjects
        // Staff, doctors, and even the patient portal (Phase 3) all log in here.
        // -------------------------------------------------------------------
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');                        // bcrypt cost 12 (SRS §12.2)
            $table->string('avatar_url')->nullable();
            $table->enum('user_type', ['staff', 'patient', 'system'])->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('mfa_secret')->nullable();          // TOTP
            $table->boolean('mfa_enabled')->default(false);
            $table->json('preferences')->nullable();           // UI prefs, notification opt-ins
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_type');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // -------------------------------------------------------------------
        // Staff — non-clinical employee profile (receptionist, cashier, etc.)
        // -------------------------------------------------------------------
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 30)->nullable();
            $table->string('designation')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------------------
        // Providers — clinical staff (doctors, nurses)
        // A provider can place orders, write notes, and be scheduled.
        // -------------------------------------------------------------------
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('provider_type', ['doctor', 'nurse', 'lab_technician', 'pharmacist', 'other'])->default('doctor');
            $table->string('registration_number', 50)->nullable();    // Medical council reg no.
            $table->string('qualification')->nullable();              // MBBS, MD, etc.
            $table->string('specialization')->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->text('bio')->nullable();                          // shown on public website
            $table->json('languages')->nullable();
            $table->boolean('is_available_online')->default(true);    // accepts online bookings
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_type');
        });

        // -------------------------------------------------------------------
        // Patients — the root of all clinical data
        // UHID is system-generated and immutable (SRS §4.1 Business Rules)
        // -------------------------------------------------------------------
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // patient portal
            $table->string('uhid', 30)->unique();                     // e.g. "NH-2026-000001"
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'unknown']);
            $table->date('date_of_birth');
            $table->boolean('is_dob_estimated')->default(false);
            $table->string('blood_group', 5)->nullable();             // A+, O-, AB+, etc.
            $table->string('marital_status', 20)->nullable();
            $table->string('occupation')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_alt', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->json('emergency_contact')->nullable();            // name, phone, relationship
            $table->enum('payer_type', ['self', 'insurance', 'corporate', 'government', 'charity'])->default('self');
            $table->string('insurance_provider')->nullable();
            $table->string('insurance_number')->nullable();
            $table->string('referred_by')->nullable();                // doctor / person / source
            $table->json('national_id')->nullable();                  // { type, number } e.g. Aadhaar
            $table->text('notes')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_deceased')->default(false);
            $table->date('deceased_date')->nullable();
            $table->foreignId('merged_into_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'phone']);
            $table->index(['first_name', 'last_name']);
            $table->index('date_of_birth');
            $table->index('is_deceased');
        });

        // Patient documents — ID proof, insurance, referrals, scans (SRS §4.1)
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('document_type', ['id_proof', 'insurance_card', 'referral_letter', 'consent_form', 'report', 'scan', 'other']);
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'document_type']);
        });

        // Additional patient contacts (family, referring physician, insurance contact)
        Schema::create('patient_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 50);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('can_receive_updates')->default(false);
            $table->timestamps();
        });

        // -------------------------------------------------------------------
        // Spatie Permission tables (roles + permissions) — auto-created by
        // `php artisan vendor:publish --provider="Spatie\Permission\..."`.
        // We pre-create them here so the schema is deterministic.
        // -------------------------------------------------------------------
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->string('category')->nullable();              // "Clinical", "Billing", etc.
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('patient_contacts');
        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('providers');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('facilities');
    }
};
