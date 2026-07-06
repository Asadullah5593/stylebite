<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('rating_enabled')->default(false)->after('allow_shares');
            $table->string('dish_name', 191)->nullable()->after('location_name');
            $table->string('restaurant', 191)->nullable()->after('dish_name');
            $table->unsignedTinyInteger('food_rating')->nullable()->after('rating_count');
            $table->unsignedTinyInteger('service_rating')->nullable()->after('food_rating');
            $table->unsignedTinyInteger('staff_rating')->nullable()->after('service_rating');
            $table->unsignedTinyInteger('ambience_rating')->nullable()->after('staff_rating');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'rating_enabled',
                'dish_name',
                'restaurant',
                'food_rating',
                'service_rating',
                'staff_rating',
                'ambience_rating',
            ]);
        });
    }
};
