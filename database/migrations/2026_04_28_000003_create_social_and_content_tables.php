<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('post_type', ['outfit', 'food', 'reel', 'memory', 'contest_submission']);
            $table->enum('content_type', ['fashion', 'food', 'mixed', 'text_only'])->default('mixed');
            $table->enum('media_kind', ['none', 'image', 'video', 'carousel', 'mixed'])->default('none');
            $table->enum('feed_type', ['style', 'bite'])->nullable();
            $table->text('caption')->nullable();
            $table->string('location_name', 255)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->enum('visibility', ['public', 'private', 'followers_only'])->default('public');
            $table->enum('status', ['draft', 'published', 'archived', 'under_review', 'removed'])->default('draft');
            $table->enum('moderation_status', ['clean', 'flagged', 'restricted', 'blocked'])->default('clean');
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_shares')->default(true);
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
            $table->unsignedInteger('save_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->dateTime('posted_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $this->addLifecycleColumns($table);

            $table->index('user_id');
            $table->index('post_type');
            $table->index('feed_type');
            $table->index(['visibility', 'status']);
            $table->index('city');
            $table->index('created_at');
            $table->index('posted_at');
            $table->index('deleted_at');
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('upload_id')->nullable()->constrained('media_uploads')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('media_type', ['image', 'video']);
            $table->enum('media_role', ['original', 'thumbnail', 'preview', 'cover'])->default('original');
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
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('processing_status', ['pending', 'processing', 'ready', 'failed'])->default('ready');
            $this->addLifecycleColumns($table);

            $table->index('post_id');
            $table->index('upload_id');
            $table->index('media_type');
            $table->index('sort_order');
            $table->index('cache_key');
        });

        Schema::create('post_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->restrictOnDelete()->cascadeOnUpdate();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['post_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('post_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating_value');
            $this->addTimestamps($table);

            $table->unique(['post_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['active', 'hidden', 'deleted', 'blocked'])->default('active');
            $table->enum('moderation_status', ['clean', 'flagged', 'restricted'])->default('clean');
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_blocked')->default(false);
            $this->addLifecycleColumns($table);

            $table->index('post_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('deleted_at');
        });

        Schema::create('comment_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('parent_reply_id')->nullable()->constrained('comment_replies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['active', 'hidden', 'deleted', 'blocked'])->default('active');
            $table->enum('moderation_status', ['clean', 'flagged', 'restricted'])->default('clean');
            $table->unsignedInteger('like_count')->default(0);
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_blocked')->default(false);
            $this->addLifecycleColumns($table);

            $table->index('comment_id');
            $table->index('user_id');
            $table->index('parent_reply_id');
            $table->index('created_at');
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['post_id', 'user_id']);
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['comment_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('reply_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reply_id')->constrained('comment_replies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['reply_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('post_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('share_channel', ['copy_link', 'direct_message', 'story', 'external'])->default('copy_link');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('created_at')->useCurrent();

            $table->index('post_id');
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('created_at');
        });

        Schema::create('saved_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('collection_name', 100)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['user_id', 'post_id']);
            $table->index('post_id');
            $table->index('created_at');
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('device_id', 191)->nullable();
            $table->enum('view_source', ['feed', 'reel', 'detail', 'explore', 'profile'])->default('feed');
            $table->unsignedInteger('watch_seconds')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('post_id');
            $table->index('viewer_user_id');
            $table->index('created_at');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['vibe_request', 'like', 'comment', 'reply', 'follow', 'contest', 'message', 'system']);
            $table->enum('entity_type', ['post', 'comment', 'reply', 'contest', 'message', 'user', 'system']);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('title', 191)->nullable();
            $table->string('body', 500)->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->string('action_url', 1024)->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->dateTime('push_sent_at')->nullable();
            $table->dateTime('email_sent_at')->nullable();
            $table->enum('delivery_status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $this->addLifecycleColumns($table);

            $table->index('recipient_user_id');
            $table->index('actor_user_id');
            $table->index('type');
            $table->index(['is_read', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('query', 255);
            $table->json('filters_json')->nullable();
            $table->enum('result_scope', ['users', 'posts', 'reels', 'food', 'contests', 'all'])->default('all');
            $table->dateTime('last_used_at')->nullable();
            $this->addTimestamps($table);

            $table->index('user_id');
            $table->index('query');
            $table->index('last_used_at');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('target_type', ['user', 'post', 'comment', 'reply', 'message', 'contest']);
            $table->unsignedBigInteger('target_id');
            $table->enum('reason', ['spam', 'harassment', 'hate', 'nudity', 'violence', 'copyright', 'fake', 'other']);
            $table->string('description', 1000)->nullable();
            $table->enum('status', ['open', 'under_review', 'resolved', 'rejected'])->default('open');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('resolution_notes', 1000)->nullable();
            $this->addTimestamps($table);

            $table->index('reporter_user_id');
            $table->index(['target_type', 'target_id']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('target_type', ['user', 'post', 'comment', 'reply', 'contest']);
            $table->unsignedBigInteger('target_id');
            $table->enum('action', ['warn', 'hide', 'remove', 'ban', 'unban', 'restrict', 'restore']);
            $table->string('reason', 500)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('moderator_user_id');
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('actor_type', ['user', 'system', 'admin'])->default('user');
            $table->string('event_name', 120);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('event_name');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('saved_posts');
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('reply_likes');
        Schema::dropIfExists('comment_likes');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('comment_replies');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_ratings');
        Schema::dropIfExists('post_tags');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
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
