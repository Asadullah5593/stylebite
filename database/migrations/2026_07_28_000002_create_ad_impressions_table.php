<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_impressions', function (Blueprint $table) {
            $table->id();
            // Viewer who saw the ad.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            // Reel the ad was tied to (null for scroll/platform ads).
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete()->cascadeOnUpdate();
            // Reel owner who earns the share (null for scroll ads).
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('ad_type', ['scroll', 'mid_reel']);
            $table->string('ad_unit_id', 191)->nullable();
            // Client-provided unique id (e.g. AdMob impression id) for de-dup.
            $table->string('impression_ref', 191)->nullable()->unique();
            // AdMob paid-event revenue for this single impression, in `currency_code`.
            $table->decimal('revenue', 16, 8)->default(0);
            $table->char('currency_code', 3)->default('USD');
            // Split at the moment the impression was recorded.
            $table->decimal('share_percent', 5, 2)->default(0);
            $table->decimal('owner_share', 16, 8)->default(0);
            $table->decimal('admin_share', 16, 8)->default(0);
            $table->enum('status', ['pending', 'settled', 'rejected'])->default('pending');
            $table->foreignId('settled_transaction_id')->nullable()->constrained('earning_transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('viewed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('post_id');
            $table->index(['owner_user_id', 'status']);
            $table->index('ad_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_impressions');
    }
};
