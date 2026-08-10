<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            // Which audience, and its parameters (city names, user ids, day window).
            $table->string('audience_type', 32);
            $table->json('audience_payload')->nullable();
            $table->string('audience_label', 191)->nullable();

            $table->string('title', 191);
            $table->string('body', 500);
            $table->string('action_url', 1024)->nullable();
            $table->string('image_url', 1024)->nullable();

            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');

            // Recipient count is snapshotted when the campaign starts so progress
            // stays meaningful even as the underlying audience shifts mid-send.
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            // Keyset cursor: the highest user id already processed. This is what
            // makes a campaign resumable across cron ticks — the worker exits
            // every ~50s on shared hosting and picks up exactly here.
            $table->unsignedBigInteger('last_user_id')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_by_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
