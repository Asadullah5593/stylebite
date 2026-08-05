<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // The dashboard's Reels card counts posts by media_kind, both in
            // total and filtered to published. post_type already has an index
            // (Food Reviews), but media_kind had none — every dashboard load
            // was scanning the whole posts table to count reels.
            $table->index(['media_kind', 'status'], 'posts_media_kind_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_media_kind_status_index');
        });
    }
};
