<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admins can now delete a user outright rather than soft-deleting them.
 *
 * These three tables pointed at `users` with ON DELETE CASCADE, so erasing an
 * agent would also erase their money and audit trail — total revenue would drop
 * retroactively and the rejection-reason analytics would lose their history.
 *
 * Switching them to ON DELETE SET NULL keeps the business record and drops only
 * the link to the person, which is what "remove the user" should mean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->unsignedBigInteger('agent_id')->nullable()->change();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('lead_feedback', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->unsignedBigInteger('agent_id')->nullable()->change();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->unsignedBigInteger('agent_id')->nullable()->change();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Rows orphaned by a deletion cannot satisfy a NOT NULL column again,
        // so clear them out before restoring the stricter constraint.
        foreach (['transactions', 'lead_feedback', 'lead_assignments'] as $table) {
            \Illuminate\Support\Facades\DB::table($table)->whereNull('agent_id')->delete();
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->unsignedBigInteger('agent_id')->nullable(false)->change();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('lead_feedback', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->unsignedBigInteger('agent_id')->nullable(false)->change();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->unsignedBigInteger('agent_id')->nullable(false)->change();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
