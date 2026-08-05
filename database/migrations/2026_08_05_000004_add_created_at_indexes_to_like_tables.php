<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The dashboard's Likes card compares this period against the previous
        // one. post_likes, comments, comment_replies and post_shares already
        // index created_at; these two were the only engagement tables without it.
        Schema::table('comment_likes', function (Blueprint $table) {
            $table->index('created_at', 'comment_likes_created_at_index');
        });

        Schema::table('reply_likes', function (Blueprint $table) {
            $table->index('created_at', 'reply_likes_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('comment_likes', function (Blueprint $table) {
            $table->dropIndex('comment_likes_created_at_index');
        });

        Schema::table('reply_likes', function (Blueprint $table) {
            $table->dropIndex('reply_likes_created_at_index');
        });
    }
};
