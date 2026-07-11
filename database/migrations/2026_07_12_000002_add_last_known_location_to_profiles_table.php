<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Last coordinates the app sent with a feed request; used as the
            // nearby-feed fallback when a request arrives without lat/lng.
            $table->decimal('last_known_lat', 10, 7)->nullable()->after('country');
            $table->decimal('last_known_lng', 10, 7)->nullable()->after('last_known_lat');
            $table->dateTime('last_located_at')->nullable()->after('last_known_lng');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['last_known_lat', 'last_known_lng', 'last_located_at']);
        });
    }
};
