<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Cached ad-eligibility, refreshed on the eligibility endpoint and by
            // stylebite:refresh-ad-eligibility. Lets the (hot) reels feed and the
            // impressions endpoint read a boolean instead of recomputing the
            // expensive watch-hours aggregate per request.
            $table->boolean('ad_eligible')->default(false)->after('is_verified_badge');
            $table->dateTime('ad_eligible_at')->nullable()->after('ad_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['ad_eligible', 'ad_eligible_at']);
        });
    }
};
