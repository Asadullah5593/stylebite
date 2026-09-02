<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contest type used to be an enum that did two jobs at once: it was shown to
     * users AND it decided how the contest behaves (city enrollment windows,
     * one_vs_one duels, submission gating for group/brand). Making it free text
     * would have silently disabled all of that, so the behaviour moves to its own
     * column and contest_type becomes the label the creator types.
     *
     * voting_type carried no logic at all, so it simply becomes free text.
     * visibility was validated, stored and echoed back but nothing ever branched
     * on it, so it goes.
     */
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->enum('contest_behavior_type', ['one_vs_one', 'group', 'city', 'brand'])
                ->default('city')
                ->after('contest_type');

            // Exactly one contest may be featured at a time; the index keeps the
            // "which one is it" lookup cheap.
            $table->boolean('is_featured')->default(false)->after('is_blocked');
            $table->index('is_featured');
        });

        // Preserve behaviour for every existing contest before contest_type loses
        // its enum constraint.
        DB::statement('UPDATE contests SET contest_behavior_type = contest_type');

        DB::statement('ALTER TABLE contests MODIFY contest_type VARCHAR(120) NOT NULL');
        DB::statement("ALTER TABLE contests MODIFY voting_type VARCHAR(120) NOT NULL DEFAULT 'community'");

        Schema::table('contests', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->enum('visibility', ['public', 'private', 'followers_only'])
                ->default('public')
                ->after('status');
        });

        // Rows whose contest_type is now free text cannot fit the old enum, so the
        // behaviour column is what we restore from.
        DB::statement('UPDATE contests SET contest_type = contest_behavior_type');
        DB::statement("ALTER TABLE contests MODIFY contest_type ENUM('one_vs_one','group','city','brand') NOT NULL");
        DB::statement("ALTER TABLE contests MODIFY voting_type ENUM('community','jury','hybrid') NOT NULL DEFAULT 'community'");

        Schema::table('contests', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn(['contest_behavior_type', 'is_featured']);
        });
    }
};
