<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comment_replies', function (Blueprint $table) {
            $table->unsignedInteger('reply_count')->default(0)->after('like_count');
        });
    }

    public function down(): void
    {
        Schema::table('comment_replies', function (Blueprint $table) {
            $table->dropColumn('reply_count');
        });
    }
};
