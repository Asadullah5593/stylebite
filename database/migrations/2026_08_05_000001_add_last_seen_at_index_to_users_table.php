<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // DAU/MAU on the admin dashboard range-scan this column; without an
            // index every dashboard load is a full table scan.
            $table->index('last_seen_at', 'users_last_seen_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_last_seen_at_index');
        });
    }
};
