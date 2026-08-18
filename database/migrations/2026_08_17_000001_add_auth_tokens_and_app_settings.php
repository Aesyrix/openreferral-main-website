<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The OTP flow is two-legged: verifying a code issues a short-lived
        // verification_token / reset_token that the next call must present.
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('token_hash', 255)->nullable()->after('code_hash');
            $table->timestamp('token_expires_at')->nullable()->after('expires_at');
            $table->string('ip_address', 45)->nullable()->after('token_expires_at');
        });

        // Editable operational settings for the admin dashboard.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::table('email_log', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->dropColumn(['token_hash', 'token_expires_at', 'ip_address']);
        });

        Schema::dropIfExists('app_settings');

        Schema::table('email_log', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
