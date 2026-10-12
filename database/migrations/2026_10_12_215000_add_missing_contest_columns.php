<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            if (! Schema::hasColumn('contests', 'subtitle')) {
                $table->string('subtitle', 255)->nullable();
            }

            if (! Schema::hasColumn('contests', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('contests', 'visibility')) {
                $table->enum('visibility', ['public', 'private', 'followers_only'])->default('public');
            }

            if (! Schema::hasColumn('contests', 'city')) {
                $table->string('city', 120)->nullable();
            }

            if (! Schema::hasColumn('contests', 'country')) {
                $table->string('country', 120)->nullable();
            }

            if (! Schema::hasColumn('contests', 'max_participants')) {
                $table->unsignedInteger('max_participants')->nullable();
            }

            if (! Schema::hasColumn('contests', 'entry_fee')) {
                $table->decimal('entry_fee', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('contests', 'prize_pool')) {
                $table->decimal('prize_pool', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('contests', 'voting_type')) {
                $table->enum('voting_type', ['community', 'jury', 'hybrid'])->default('community');
            }

            if (! Schema::hasColumn('contests', 'start_at')) {
                $table->dateTime('start_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'end_at')) {
                $table->dateTime('end_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'result_at')) {
                $table->dateTime('result_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'cover_image_url')) {
                $table->string('cover_image_url', 1024)->nullable();
            }

            if (! Schema::hasColumn('contests', 'banner_image_url')) {
                $table->string('banner_image_url', 1024)->nullable();
            }

            if (! Schema::hasColumn('contests', 'participant_count')) {
                $table->unsignedInteger('participant_count')->default(0);
            }

            if (! Schema::hasColumn('contests', 'submission_count')) {
                $table->unsignedInteger('submission_count')->default(0);
            }

            if (! Schema::hasColumn('contests', 'total_vote_count')) {
                $table->unsignedInteger('total_vote_count')->default(0);
            }

            if (! Schema::hasColumn('contests', 'is_reported')) {
                $table->boolean('is_reported')->default(false);
            }

            if (! Schema::hasColumn('contests', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false);
            }

            if (! Schema::hasColumn('contests', 'deleted_at')) {
                $table->dateTime('deleted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Columns may already have existed before this repair. Leave them in place.
    }
};
