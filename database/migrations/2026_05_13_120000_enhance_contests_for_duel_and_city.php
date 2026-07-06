<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->dateTime('enrollment_start_at')->nullable()->after('result_at');
            $table->dateTime('enrollment_end_at')->nullable()->after('enrollment_start_at');
            $table->dateTime('voting_start_at')->nullable()->after('enrollment_end_at');
            $table->dateTime('voting_end_at')->nullable()->after('voting_start_at');
            $table->enum('challenge_scope', ['followers_only', 'global'])->nullable()->after('voting_end_at');
            $table->foreignId('winner_user_id')->nullable()->after('challenge_scope')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('winner_team_id')->nullable()->after('winner_user_id')->constrained('contest_teams')->nullOnDelete()->cascadeOnUpdate();
            $table->index('enrollment_end_at');
            $table->index('voting_end_at');
        });

        Schema::create('contest_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('receiver_user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('request_type', ['invite', 'join_request'])->default('invite');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['contest_id', 'sender_user_id', 'receiver_user_id', 'request_type'], 'uq_contest_invites_unique');
            $table->index(['receiver_user_id', 'status']);
            $table->index(['contest_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_invitations');

        Schema::table('contests', function (Blueprint $table) {
            $table->dropIndex(['enrollment_end_at']);
            $table->dropIndex(['voting_end_at']);
            $table->dropConstrainedForeignId('winner_user_id');
            $table->dropConstrainedForeignId('winner_team_id');
            $table->dropColumn([
                'enrollment_start_at',
                'enrollment_end_at',
                'voting_start_at',
                'voting_end_at',
                'challenge_scope',
            ]);
        });
    }
};
