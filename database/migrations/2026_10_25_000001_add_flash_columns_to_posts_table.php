<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_flash')->default(false)->after('is_story');
            $table->index(['is_flash', 'expires_at'], 'posts_flash_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_flash_expiry_index');
            $table->dropColumn('is_flash');
        });
    }
};
