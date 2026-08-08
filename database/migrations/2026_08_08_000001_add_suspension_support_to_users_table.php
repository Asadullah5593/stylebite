<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'suspended' becomes its own state so it can never collide with
        // 'inactive' (= registered but email not verified). Before this,
        // admin-suspended users who had never verified could self-reactivate
        // through the email-OTP flow, silently undoing the suspension.
        DB::statement("
            ALTER TABLE users
            MODIFY status ENUM('active','inactive','suspended','banned','deleted') NOT NULL DEFAULT 'active'
        ");

        Schema::table('users', function (Blueprint $table) {
            // NULL on a suspended user means indefinite (until an admin lifts it).
            $table->dateTime('suspended_until')->nullable()->after('status');
            // Denormalised copy of the latest ban/suspension reason so the
            // login error can show it without joining moderation_actions.
            $table->string('status_reason', 500)->nullable()->after('suspended_until');
        });

        // Legacy admin "suspends" wrote status='inactive'. A verified inactive
        // account can only have come from that path, so move them to the new
        // state. Unverified ones really are pending-registration — leave them.
        DB::table('users')
            ->where('status', 'inactive')
            ->whereNotNull('email_verified_at')
            ->update(['status' => 'suspended']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('status', 'suspended')
            ->update(['status' => 'inactive']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_until', 'status_reason']);
        });

        DB::statement("
            ALTER TABLE users
            MODIFY status ENUM('active','inactive','banned','deleted') NOT NULL DEFAULT 'active'
        ");
    }
};
