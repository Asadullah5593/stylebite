<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support tickets get their own schema end to end (client decision,
     * 2026-08-09) rather than reusing conversations/messages. That keeps the
     * chat tables — and the eight `where('type','direct')` filters in
     * Api/ChatController — completely untouched.
     */
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // Human-quotable handle, e.g. TK-000042. Users cite it in emails and
            // reviews, so it must not be the raw primary key.
            $table->string('reference', 20)->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            // Bug lives here, not in `reports`: a bug needs a conversation plus
            // device metadata, neither of which the reports table can hold.
            $table->enum('category', ['bug', 'payment', 'account', 'content_appeal', 'other'])->default('other');
            $table->string('subject', 191);

            $table->enum('status', ['open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            // Who spoke last drives the "waiting on us" queue without a join.
            $table->enum('last_reply_by', ['user', 'staff'])->default('user');
            $table->dateTime('last_reply_at')->nullable();
            $table->unsignedInteger('messages_count')->default(0);

            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            // Context the app can attach automatically, mainly for bug reports.
            $table->string('app_version', 32)->nullable();
            $table->string('platform', 16)->nullable();
            $table->string('device_model', 120)->nullable();
            $table->string('os_version', 32)->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('priority');
            $table->index('category');
            $table->index('assigned_to_user_id');
            $table->index(['status', 'last_reply_at']);
            $table->index('created_at');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnUpdate()->cascadeOnDelete();

            // Null author = system note (status changes, automated text).
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('author_type', ['user', 'staff', 'system'])->default('user');

            $table->text('body');

            // Internal notes are staff-only and must never reach the mobile API.
            $table->boolean('is_internal')->default(false);

            $table->dateTime('read_by_user_at')->nullable();
            $table->dateTime('read_by_staff_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('support_ticket_id');
            $table->index(['support_ticket_id', 'is_internal']);
            $table->index('author_user_id');
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_message_id')->constrained('support_ticket_messages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('file_path', 1024);
            $table->string('original_file_name', 191)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('support_ticket_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
