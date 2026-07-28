<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Ads no longer use a manual opt-in switch: creators earn
            // automatically once eligible, so these columns are unused.
            if (Schema::hasColumn('profiles', 'ads_enabled')) {
                $table->dropColumn('ads_enabled');
            }
            if (Schema::hasColumn('profiles', 'ads_enabled_at')) {
                $table->dropColumn('ads_enabled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('ads_enabled')->default(false)->after('is_verified_badge');
            $table->dateTime('ads_enabled_at')->nullable()->after('ads_enabled');
        });
    }
};
