<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            // 'register' = the account-activation OTP, 'login' = the 2FA code
            // required on every password login. Kept in one table so both flows
            // share the hashing, expiry, and attempt-lockout mechanics.
            $table->string('purpose', 20)->default('register')->after('email');

            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'purpose']);
            $table->dropColumn('purpose');
        });
    }
};
