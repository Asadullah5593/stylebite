<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sessions now live 24 hours from login. Tokens issued before this change
     * carry up to 30 days of remaining lifetime, so cap them at the new
     * maximum from deploy time instead of letting them ride out the old TTL.
     */
    public function up(): void
    {
        DB::table('user_sessions')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now()->addHours(24))
            ->update(['expires_at' => now()->addHours(24)]);
    }

    public function down(): void
    {
        // Irreversible data fix: the original expiry timestamps are gone.
    }
};
