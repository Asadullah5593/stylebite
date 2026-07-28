<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE earning_transactions
            MODIFY source_type ENUM('contest_reward', 'engagement_bonus', 'referral_bonus', 'ad_revenue', 'withdrawal', 'adjustment') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE earning_transactions
            MODIFY source_type ENUM('contest_reward', 'engagement_bonus', 'referral_bonus', 'withdrawal', 'adjustment') NOT NULL
        ");
    }
};
