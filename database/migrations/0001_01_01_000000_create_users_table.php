<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('username', 50)->unique();
            $table->string('phone_country_code', 8)->nullable();
            $table->string('phone_number', 25)->nullable();
            $table->string('password_hash');
            $table->string('full_name', 120)->nullable();
            $table->enum('role', ['user', 'creator', 'moderator', 'admin'])->default('user');
            $table->enum('status', ['active', 'inactive', 'banned', 'deleted'])->default('active');
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->boolean('is_two_factor_enabled')->default(false);
            $table->string('avatar_url', 1024)->nullable();
            $table->string('cover_url', 1024)->nullable();
            $table->string('locale', 16)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $this->addLifecycleColumns($table);

            $table->unique(['phone_country_code', 'phone_number']);
            $table->index('status');
            $table->index('created_at');
            $table->index('deleted_at');
        });

        Schema::create('user_auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('provider', ['email', 'google', 'apple', 'facebook']);
            $table->string('provider_user_id', 191);
            $table->string('provider_email', 191)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $this->addTimestamps($table);

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });

        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('email', 191);
            $table->string('reset_token_hash');
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('email');
            $table->index('expires_at');
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('session_token_hash')->unique();
            $table->string('device_id', 191)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->enum('platform', ['ios', 'android', 'web', 'desktop']);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $this->addTimestamps($table);

            $table->index('user_id');
            $table->index('expires_at');
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('device_id', 191);
            $table->enum('platform', ['ios', 'android', 'web']);
            $table->string('push_token', 512);
            $table->string('app_version', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_used_at')->nullable();
            $this->addTimestamps($table);

            $table->unique(['platform', 'push_token']);
            $table->unique(['user_id', 'device_id']);
            $table->index('user_id');
            $table->index('is_active');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('display_name', 120)->nullable();
            $table->string('bio', 500)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary', 'prefer_not_to_say', 'other'])->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedInteger('vibe_count')->default(0);
            $table->unsignedInteger('follower_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedInteger('post_count')->default(0);
            $table->unsignedInteger('reel_count')->default(0);
            $table->boolean('is_private')->default(false);
            $table->enum('visibility', ['public', 'private', 'followers_only'])->default('public');
            $this->addLifecycleColumns($table);

            $table->index('visibility');
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('language', 16)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('dark_mode')->default(false);
            $table->boolean('autoplay_videos')->default(true);
            $table->boolean('push_notifications_enabled')->default(true);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('message_notifications_enabled')->default(true);
            $table->boolean('contest_notifications_enabled')->default(true);
            $table->enum('profile_visibility', ['public', 'private', 'followers_only'])->default('public');
            $table->enum('allow_tagging', ['everyone', 'followers', 'no_one'])->default('everyone');
            $table->enum('allow_mentions', ['everyone', 'followers', 'no_one'])->default('everyone');
            $table->enum('allow_messages', ['everyone', 'followers', 'no_one'])->default('everyone');
            $table->boolean('show_activity_status')->default(true);
            $table->boolean('save_to_gallery_on_upload')->default(false);
            $this->addTimestamps($table);
        });

        Schema::create('user_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('following_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'blocked'])->default('accepted');
            $this->addLifecycleColumns($table);

            $table->unique(['follower_user_id', 'following_user_id']);
            $table->index('following_user_id');
            $table->index('status');
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('reason', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['blocker_user_id', 'blocked_user_id']);
            $table->index('blocked_user_id');
        });

        Schema::create('media_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('source', ['camera', 'gallery', 'import', 'external'])->default('gallery');
            $table->enum('upload_type', ['post_media', 'profile_avatar', 'profile_cover', 'message_attachment', 'memory_media', 'contest_asset']);
            $table->enum('media_type', ['image', 'video']);
            $table->string('original_file_name', 255)->nullable();
            $table->string('file_path', 1024)->nullable();
            $table->string('file_url', 1024);
            $table->string('thumbnail_url', 1024)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->enum('storage_type', ['local', 's3', 'cdn', 'gcs'])->default('cdn');
            $table->string('cache_key', 255)->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->enum('upload_status', ['queued', 'uploading', 'processing', 'ready', 'failed'])->default('queued');
            $table->string('failure_reason', 255)->nullable();
            $table->dateTime('uploaded_at')->nullable();
            $this->addLifecycleColumns($table);

            $table->index('user_id');
            $table->index('upload_type');
            $table->index('upload_status');
            $table->index('created_at');
            $table->index('cache_key');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('normalized_name', 100)->unique();
            $table->unsignedInteger('usage_count')->default(0);
            $this->addTimestamps($table);

            $table->index('usage_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
        Schema::dropIfExists('media_uploads');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_follows');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('user_auth_providers');
        Schema::dropIfExists('users');
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
