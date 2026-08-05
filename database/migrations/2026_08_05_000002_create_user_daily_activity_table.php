<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user per day they used the app. `users.last_seen_at` is
        // overwritten on every request, so it can answer "active right now" but
        // never "how many were active last Tuesday" — this table keeps that history.
        Schema::create('user_daily_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            // Calendar day in the reporting timezone, not UTC.
            $table->date('activity_date');
            $table->dateTime('created_at')->nullable();

            // Makes the daily write idempotent under concurrent requests.
            $table->unique(['user_id', 'activity_date'], 'user_daily_activity_user_date_unique');
            // Covering index: DAU is a count on this index alone, MAU a distinct on it.
            $table->index(['activity_date', 'user_id'], 'user_daily_activity_date_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_activity');
    }
};
