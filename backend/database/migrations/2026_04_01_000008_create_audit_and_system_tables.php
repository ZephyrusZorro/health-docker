<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain 8 of 8: AUDIT & EVENTS
 *
 * Every sensitive action writes to audit_logs with a before/after diff.
 * The notification pipeline records queued and delivered messages.
 * system_events captures domain-level events for eventual event sourcing.
 *
 * SRS reference: §12.3 Audit & Monitoring, §4.13 Notifications & Automation
 */
return new class extends Migration {
    public function up(): void
    {
        // -------------------------------------------------------------------
        // Audit logs — THE compliance backbone (SRS §9 Business Rules, §12.3)
        // Every sensitive action: login, clinical edit, prescription,
        // billing event, user management, config change.
        // -------------------------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email')->nullable();             // denormalized — survives user delete
            $table->string('user_role', 50)->nullable();
            $table->string('event');                              // "patient.created", "prescription.signed"
            $table->string('category', 30)->nullable();           // "auth", "clinical", "billing", "admin"
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->nullableMorphs('auditable');                  // polymorphic target
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('context')->nullable();                  // extra metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 36)->nullable();         // trace across logs
            $table->timestamp('created_at')->useCurrent();

            $table->index(['facility_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index('severity');
        });

        // Simpler activity log (who did what recently — for UI feeds)
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->string('log_name', 50)->default('default');
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['log_name', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // -------------------------------------------------------------------
        // Notifications (SRS §4.13)
        // Notifications are fan-outs; message_logs track actual delivery.
        // -------------------------------------------------------------------
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('code', 50);                           // "appointment_reminder_24h"
            $table->string('name');
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'push', 'in_app']);
            $table->string('subject')->nullable();
            $table->text('body');                                 // with {{placeholders}}
            $table->string('language', 10)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'code', 'channel', 'language']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('notifiable');                 // recipient entity
            $table->string('template_code', 50)->nullable();
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'push', 'in_app']);
            $table->string('recipient');                          // email / phone
            $table->string('subject')->nullable();
            $table->text('body');
            $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'failed', 'bounced', 'read'])->default('queued');
            $table->string('provider', 50)->nullable();           // ses/twilio/etc.
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['facility_id', 'channel', 'status']);
            $table->index(['recipient', 'created_at']);
            $table->index('status');
        });

        // Queued jobs — Laravel framework tables (for Redis we still store failed jobs in DB)
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // -------------------------------------------------------------------
        // System events — domain events for future event sourcing / projections
        // -------------------------------------------------------------------
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');                         // "patient.registered"
            $table->string('aggregate_type')->nullable();         // "Patient"
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->string('correlation_id', 36)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['event_name', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        // System-wide settings (key-value with namespaces)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('namespace', 50);                      // "billing", "notifications"
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'encrypted'])->default('string');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'namespace', 'key']);
        });

        // Content pages for the public website (CMS, SRS §4.11)
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('slug');
            $table->string('title');
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->longText('content');                          // markdown or HTML
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['facility_id', 'slug']);
        });

        // Announcements / health tips on website
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->string('image_url')->nullable();
            $table->enum('type', ['announcement', 'health_tip', 'news', 'emergency'])->default('announcement');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('system_events');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('audit_logs');
    }
};
