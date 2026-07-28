<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Whether the creator has turned ads on for their reels (only
            // allowed once they meet the ad eligibility criteria).
            $table->boolean('ads_enabled')->default(false)->after('is_verified_badge');
            $table->dateTime('ads_enabled_at')->nullable()->after('ads_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['ads_enabled', 'ads_enabled_at']);
        });
    }
};
