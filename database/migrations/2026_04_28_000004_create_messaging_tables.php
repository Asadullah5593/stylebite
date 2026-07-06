<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'group', 'support'])->default('direct');
            $table->string('title', 191)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $this->addLifecycleColumns($table);

            $table->index('type');
            $table->index('last_message_at');
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('role', ['member', 'admin', 'owner'])->default('member');
            $table->enum('status', ['active', 'left', 'removed', 'blocked'])->default('active');
            $table->dateTime('joined_at')->useCurrent();
            $table->dateTime('left_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->dateTime('last_read_at')->nullable();
            $table->dateTime('mute_until')->nullable();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('message_type', ['text', 'image', 'video', 'system'])->default('text');
            $table->text('body')->nullable();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('messages')->nullOnDelete()->cascadeOnUpdate();
            $table->boolean('is_edited')->default(false);
            $table->dateTime('edited_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('sent_at')->useCurrent();
            $table->dateTime('delivered_at')->nullable();
            $this->addTimestamps($table);

            $table->index('conversation_id');
            $table->index('sender_user_id');
            $table->index('sent_at');
            $table->index('reply_to_message_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('last_message_id')->references('id')->on('messages')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('upload_id')->nullable()->constrained('media_uploads')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('media_type', ['image', 'video', 'file']);
            $table->string('file_url', 1024);
            $table->string('thumbnail_url', 1024)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('cache_key', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('message_id');
            $table->index('upload_id');
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('read_at')->useCurrent();

            $table->unique(['message_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
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
