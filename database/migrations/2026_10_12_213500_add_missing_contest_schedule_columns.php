<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            if (! Schema::hasColumn('contests', 'enrollment_start_at')) {
                $table->dateTime('enrollment_start_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'enrollment_end_at')) {
                $table->dateTime('enrollment_end_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'voting_start_at')) {
                $table->dateTime('voting_start_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'voting_end_at')) {
                $table->dateTime('voting_end_at')->nullable();
            }

            if (! Schema::hasColumn('contests', 'challenge_scope')) {
                $table->string('challenge_scope', 32)->nullable();
            }

            if (! Schema::hasColumn('contests', 'winner_user_id')) {
                $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('contests', 'winner_team_id')) {
                $table->foreignId('winner_team_id')->nullable()->constrained('contest_teams')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        if (! Schema::hasTable('contest_invitations')) {
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
    }

    public function down(): void
    {
        // Columns may already have existed before this repair. Leave them in place.
    }
};
