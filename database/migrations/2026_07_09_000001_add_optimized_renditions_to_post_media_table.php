<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            // Mobile-optimized rendition (compressed image / <=720p transcoded video).
            $table->string('optimized_path', 1024)->nullable()->after('thumbnail_url');
            $table->string('optimized_url', 1024)->nullable()->after('optimized_path');
            $table->unsignedInteger('optimized_width')->nullable()->after('optimized_url');
            $table->unsignedInteger('optimized_height')->nullable()->after('optimized_width');
            $table->unsignedBigInteger('optimized_size_bytes')->nullable()->after('optimized_height');
            $table->dateTime('optimized_at')->nullable()->after('optimized_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            $table->dropColumn([
                'optimized_path',
                'optimized_url',
                'optimized_width',
                'optimized_height',
                'optimized_size_bytes',
                'optimized_at',
            ]);
        });
    }
};
