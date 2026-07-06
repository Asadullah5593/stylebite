<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE moderation_actions
            MODIFY target_type ENUM('user','post','comment','reply','message','contest') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE moderation_actions
            MODIFY target_type ENUM('user','post','comment','reply','contest') NOT NULL
        ");
    }
};
