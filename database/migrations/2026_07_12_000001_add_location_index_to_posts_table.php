<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Bounding-box prefilter for the nearby feed (lat range scan, then lng).
            $table->index(['location_lat', 'location_lng'], 'posts_location_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_location_lat_lng_index');
        });
    }
};
