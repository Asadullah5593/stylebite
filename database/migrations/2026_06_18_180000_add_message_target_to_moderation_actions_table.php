<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE moderation_actions
            MODIFY target_type ENUM('user','post','comment','reply','message','contest') NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE moderation_actions
            MODIFY target_type ENUM('user','post','comment','reply','contest') NOT NULL
        ");
    }
};
