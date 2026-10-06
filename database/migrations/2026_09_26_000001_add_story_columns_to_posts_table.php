<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_story')->default(false)->after('media_kind');
            $table->dateTime('expires_at')->nullable()->after('published_at');
            $table->index(['is_story', 'expires_at'], 'posts_story_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_story_expiry_index');
            $table->dropColumn(['is_story', 'expires_at']);
        });
    }
};
