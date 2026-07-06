<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('is_verified_badge')->default(false)->after('bio');
            $table->unsignedInteger('style_points')->default(0)->after('post_count');
            $table->unsignedInteger('current_streak_days')->default(0)->after('style_points');
            $table->string('current_streak_label', 120)->nullable()->after('current_streak_days');
            $table->unsignedInteger('contest_wins')->default(0)->after('current_streak_label');
            $table->unsignedInteger('contest_entries')->default(0)->after('contest_wins');
            $table->unsignedInteger('battle_wins')->default(0)->after('contest_entries');
            $table->string('battle_rank_label', 120)->nullable()->after('battle_wins');
        });

        Schema::create('profile_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('badge_key', 80);
            $table->string('title', 120);
            $table->string('icon_key', 80)->nullable();
            $table->enum('status', ['earned', 'locked'])->default('earned');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('earned_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'badge_key']);
            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_badges');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'is_verified_badge',
                'style_points',
                'current_streak_days',
                'current_streak_label',
                'contest_wins',
                'contest_entries',
                'battle_wins',
                'battle_rank_label',
            ]);
        });
    }
};
