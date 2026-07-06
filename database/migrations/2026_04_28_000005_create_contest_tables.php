<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('creator_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('title', 191);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->enum('category', ['admin', 'community'])->default('community');
            $table->enum('contest_type', ['one_vs_one', 'group', 'city', 'brand']);
            $table->enum('status', ['draft', 'active', 'upcoming', 'completed', 'cancelled', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'private', 'followers_only'])->default('public');
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->decimal('entry_fee', 12, 2)->default(0);
            $table->decimal('prize_pool', 12, 2)->default(0);
            $table->enum('voting_type', ['community', 'jury', 'hybrid'])->default('community');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->dateTime('result_at')->nullable();
            $table->string('cover_image_url', 1024)->nullable();
            $table->string('banner_image_url', 1024)->nullable();
            $table->unsignedInteger('participant_count')->default(0);
            $table->unsignedInteger('submission_count')->default(0);
            $table->unsignedInteger('total_vote_count')->default(0);
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_blocked')->default(false);
            $this->addLifecycleColumns($table);

            $table->index('creator_user_id');
            $table->index('status');
            $table->index(['category', 'contest_type']);
            $table->index('start_at');
        });

        Schema::create('contest_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('rule_text', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->index('contest_id');
            $table->index('sort_order');
        });

        Schema::create('contest_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('participant_role', ['creator', 'team_member', 'jury'])->default('creator');
            $table->enum('status', ['joined', 'approved', 'rejected', 'withdrawn', 'banned'])->default('joined');
            $table->dateTime('joined_at')->useCurrent();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedInteger('rank_position')->nullable();
            $table->decimal('total_score', 10, 2)->nullable();
            $this->addTimestamps($table);

            $table->unique(['contest_id', 'user_id']);
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('contest_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('city', 120)->nullable();
            $table->string('logo_url', 1024)->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->unsignedInteger('rank_position')->nullable();
            $this->addTimestamps($table);

            $table->index('contest_id');
        });

        Schema::create('contest_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_team_id')->constrained('contest_teams')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('role', ['captain', 'member'])->default('member');
            $table->dateTime('joined_at')->useCurrent();

            $table->unique(['contest_team_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('contest_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('contest_team_id')->nullable()->constrained('contest_teams')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('submission_status', ['submitted', 'approved', 'rejected', 'disqualified'])->default('submitted');
            $table->decimal('jury_score', 5, 2)->nullable();
            $table->decimal('community_score', 5, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->unsignedInteger('rank_position')->nullable();
            $table->dateTime('submitted_at')->useCurrent();
            $table->dateTime('reviewed_at')->nullable();
            $this->addTimestamps($table);

            $table->unique(['contest_id', 'user_id', 'post_id']);
            $table->index('post_id');
            $table->index('contest_team_id');
            $table->index('submission_status');
        });

        Schema::create('contest_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('contest_submissions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('voter_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('vote_type', ['community', 'jury'])->default('community');
            $table->decimal('score', 4, 2);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['submission_id', 'voter_user_id', 'vote_type']);
            $table->index('contest_id');
            $table->index('voter_user_id');
            $table->index('created_at');
        });

        Schema::create('contest_leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('period_key', 30);
            $table->string('category_key', 30);
            $table->json('payload_json');
            $table->dateTime('generated_at')->useCurrent();

            $table->index('contest_id');
            $table->index(['period_key', 'category_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_leaderboard_snapshots');
        Schema::dropIfExists('contest_votes');
        Schema::dropIfExists('contest_submissions');
        Schema::dropIfExists('contest_team_members');
        Schema::dropIfExists('contest_teams');
        Schema::dropIfExists('contest_participants');
        Schema::dropIfExists('contest_rules');
        Schema::dropIfExists('contests');
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
