<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->text('short_description')->nullable()->after('short_title');
            $table->decimal('location_lat', 10, 7)->nullable()->after('location_name');
            $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
            $table->unsignedInteger('rating_count')->default(0)->after('rating');

            $table->index(['user_id', 'memory_date']);
            $table->index(['visibility', 'status']);
        });

        Schema::table('memory_media', function (Blueprint $table) {
            $table->string('file_path', 1024)->nullable()->after('media_type');
            $table->string('mime_type', 120)->nullable()->after('thumbnail_url');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->enum('storage_type', ['local', 's3', 'cdn', 'gcs'])->default('local')->after('size_bytes');
        });

        Schema::create('memory_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['memory_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('memory_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating_value');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['memory_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('memory_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['active', 'hidden', 'deleted', 'blocked'])->default('active');
            $table->unsignedInteger('like_count')->default(0);
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();

            $table->index('memory_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_comments');
        Schema::dropIfExists('memory_ratings');
        Schema::dropIfExists('memory_likes');

        Schema::table('memory_media', function (Blueprint $table) {
            $table->dropColumn([
                'file_path',
                'mime_type',
                'size_bytes',
                'storage_type',
            ]);
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex('memories_user_id_memory_date_index');
            $table->dropIndex('memories_visibility_status_index');
            $table->dropColumn([
                'short_description',
                'location_lat',
                'location_lng',
                'rating_count',
            ]);
        });
    }
};
