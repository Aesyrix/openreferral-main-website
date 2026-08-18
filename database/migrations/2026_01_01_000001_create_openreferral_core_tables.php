<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agent Profiles
        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('title', 120)->default('Real Estate Agent');
            $table->boolean('verified')->default(false);
            $table->string('license_number', 80)->nullable();
            $table->string('license_state', 80)->nullable();
            $table->string('brokerage', 160)->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_reason', 255)->nullable();
            $table->timestamps();
        });

        // Service Areas
        Schema::create('agent_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('area', 160);
            $table->unique(['user_id', 'area']);
        });

        // User Settings
        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('push_notifications')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->string('language', 10)->default('en');
            $table->string('theme', 20)->default('system');
        });

        // 2. Auth Verification Codes
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('code_hash', 255);
            $table->string('purpose', 40); // signup, password_reset
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        // 3. Leads
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id', 20)->unique();
            $table->string('name', 160);
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('intent', 20); // buying, selling
            $table->string('property_type', 120)->nullable();
            $table->unsignedInteger('price_min')->nullable();
            $table->unsignedInteger('price_max')->nullable();
            $table->string('location', 190)->nullable();
            $table->string('timeline', 80)->nullable();
            $table->string('bedrooms', 20)->nullable();
            $table->string('bathrooms', 20)->nullable();
            $table->string('source', 80)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('lead_fee_cents')->default(0);
            $table->string('status', 30)->default('new')->index(); // new, in_progress, converted, rejected
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Lead Assignments
        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
        });

        // Lead Feedback
        Schema::create('lead_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 20); // accept, reject
            $table->string('reason', 80)->nullable(); // not_in_service_area, etc.
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Lead Status History
        Schema::create('lead_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 4. Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40); // new_lead, lead_update, feedback, agent_signup, system
            $table->string('title', 190);
            $table->string('body', 500);
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });

        // Devices (FCM)
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 400)->unique();
            $table->string('platform', 20); // android, ios, web
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Email Log
        Schema::create('email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_email', 190);
            $table->string('event', 60)->index();
            $table->string('subject', 190);
            $table->string('status', 20)->default('queued');
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. Transactions
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('type', 30)->default('lead_fee'); // lead_fee, subscription, adjustment
            $table->integer('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('completed'); // pending, completed, refunded, failed
            $table->string('reference', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
        });

        // 6. Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        // Content Pages
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('title', 190);
            $table->text('intro')->nullable();
            $table->json('body_json');
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('email_log');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('lead_status_history');
        Schema::dropIfExists('lead_feedback');
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('agent_service_areas');
        Schema::dropIfExists('agent_profiles');
        Schema::enableForeignKeyConstraints();
    }
};
