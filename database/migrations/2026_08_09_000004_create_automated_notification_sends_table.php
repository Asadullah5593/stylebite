<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency ledger for automated notifications.
     *
     * The reminder commands run hourly from cron, so without this a user would
     * be pinged about the same lapsing streak or the same closing contest once
     * an hour. The unique key makes a re-run a no-op instead.
     */
    public function up(): void
    {
        Schema::create('automated_notification_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            // 'streak_reminder' | 'contest_ending_soon'
            $table->string('kind', 40);

            // What the send was *for*: the reporting-timezone date for streaks,
            // the contest id for contest reminders. Keeps one row per real event.
            $table->string('scope_key', 60);

            $table->dateTime('created_at')->useCurrent();

            $table->unique(['user_id', 'kind', 'scope_key']);
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_notification_sends');
    }
};
