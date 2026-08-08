<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE moderation_actions
            MODIFY action ENUM('warn','hide','remove','ban','unban','suspend','unsuspend','restrict','restore') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE moderation_actions
            MODIFY action ENUM('warn','hide','remove','ban','unban','restrict','restore') NOT NULL
        ");
    }
};
