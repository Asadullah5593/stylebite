<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Personal best, kept across resets so history is not destroyed.
            $table->unsignedInteger('longest_streak_days')->default(0)->after('current_streak_label');
            // The most recent day that counted — lets the nightly job decide who
            // needs recomputing without replaying everyone's history.
            $table->date('last_streak_day')->nullable()->after('longest_streak_days');
            // An admin reset moves this boundary forward; the engine ignores every
            // day before it, so the reset survives the next recomputation.
            $table->dateTime('streak_reset_at')->nullable()->after('last_streak_day');
            // Lifetime quota of admin restores, capped by streaks.max_restores.
            $table->unsignedSmallInteger('streak_restore_count')->default(0)->after('streak_reset_at');

            // Dashboard counts/averages filter and aggregate on this column.
            $table->index('current_streak_days', 'profiles_current_streak_days_index');
        });

        // An admin restore does not write a streak number — it grants the days
        // the user missed. Recomputation then arrives at the restored streak on
        // its own, so the engine stays a pure function of the underlying data.
        Schema::create('streak_grace_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('grace_date');
            $table->string('reason', 255)->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('created_at')->nullable();

            $table->unique(['user_id', 'grace_date'], 'streak_grace_days_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_grace_days');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex('profiles_current_streak_days_index');
            $table->dropColumn([
                'longest_streak_days',
                'last_streak_day',
                'streak_reset_at',
                'streak_restore_count',
            ]);
        });
    }
};
