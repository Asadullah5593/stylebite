<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earnings_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->char('currency_code', 3)->default('USD');
            $table->decimal('available_balance', 14, 2)->default(0);
            $table->decimal('pending_balance', 14, 2)->default(0);
            $table->decimal('lifetime_earned', 14, 2)->default(0);
            $table->decimal('lifetime_withdrawn', 14, 2)->default(0);
            $table->dateTime('updated_balance_at')->nullable();
            $this->addTimestamps($table);
        });

        Schema::create('earning_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('earnings_wallets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('transaction_type', ['credit', 'debit']);
            $table->enum('source_type', ['contest_reward', 'engagement_bonus', 'referral_bonus', 'withdrawal', 'adjustment']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('completed');
            $table->string('note', 255)->nullable();
            $table->json('metadata_json')->nullable();
            $table->dateTime('processed_at')->nullable();
            $this->addTimestamps($table);

            $table->index('wallet_id');
            $table->index('user_id');
            $table->index(['source_type', 'source_id']);
            $table->index('created_at');
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('earnings_wallets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3)->default('USD');
            $table->enum('method', ['bank_transfer', 'paypal', 'stripe', 'wallet'])->default('bank_transfer');
            $table->string('account_ref', 255)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'rejected'])->default('pending');
            $table->dateTime('requested_at')->useCurrent();
            $table->dateTime('processed_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $this->addTimestamps($table);

            $table->index('wallet_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('requested_at');
        });

        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title', 191);
            $table->string('short_title', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('note', 255)->nullable();
            $table->date('memory_date')->nullable();
            $table->string('location_name', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->enum('visibility', ['public', 'private', 'followers_only'])->default('public');
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('save_count')->default(0);
            $this->addLifecycleColumns($table);

            $table->index('user_id');
            $table->index('city');
            $table->index('memory_date');
            $table->index('created_at');
        });

        Schema::create('memory_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('upload_id')->nullable()->constrained('media_uploads')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('media_type', ['image', 'video'])->default('image');
            $table->string('file_url', 1024);
            $table->string('thumbnail_url', 1024)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->index('memory_id');
            $table->index('upload_id');
            $table->index('sort_order');
        });

        Schema::create('saved_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['user_id', 'memory_id']);
            $table->index('memory_id');
        });

        Schema::create('app_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 120)->unique();
            $table->text('config_value')->nullable();
            $table->enum('value_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $this->addTimestamps($table);
        });

        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('device_token_id')->nullable()->constrained('device_tokens')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('provider', ['fcm', 'apns']);
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->text('provider_response')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('notification_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_logs');
        Schema::dropIfExists('app_configs');
        Schema::dropIfExists('saved_memories');
        Schema::dropIfExists('memory_media');
        Schema::dropIfExists('memories');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('earning_transactions');
        Schema::dropIfExists('earnings_wallets');
    }

    private function addTimestamps(Blueprint $table): void
    {
        $table->dateTime('created_at')->useCurrent();
        $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
    }

    private function addLifecycleColumns(Blueprint $table): void
    {
        $this->addTimestamps($table);
        $table->dateTime('deleted_at')->nullable();
    }
};
