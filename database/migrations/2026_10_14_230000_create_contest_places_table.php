<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contest_places')) {
            Schema::create('contest_places', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contest_id')->constrained('contests')->cascadeOnUpdate()->cascadeOnDelete();
                $table->unsignedSmallInteger('place');
                $table->string('prize_title', 120)->nullable();
                $table->decimal('prize_amount', 12, 2)->default(0);
                $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['contest_id', 'place']);
                $table->index('winner_user_id');
            });
        }

        if (! Schema::hasTable('contests') || ! Schema::hasTable('contest_places')) {
            return;
        }

        $hasWinner = Schema::hasColumn('contests', 'winner_user_id');
        $columns = ['id', 'prize_pool'];
        if ($hasWinner) {
            $columns[] = 'winner_user_id';
        }

        $now = now();

        DB::table('contests')
            ->select($columns)
            ->orderBy('id')
            ->each(function (object $contest) use ($hasWinner, $now): void {
                $amount = (float) ($contest->prize_pool ?? 0);
                $winnerId = $hasWinner ? ($contest->winner_user_id ?? null) : null;
                $exists = DB::table('contest_places')->where('contest_id', $contest->id)->exists();
                if ($exists || ($amount <= 0 && $winnerId === null)) {
                    return;
                }

                DB::table('contest_places')->insert([
                    'contest_id' => $contest->id,
                    'place' => 1,
                    'prize_title' => '1st place',
                    'prize_amount' => $contest->prize_pool ?? 0,
                    'winner_user_id' => $hasWinner ? ($contest->winner_user_id ?? null) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_places');
    }
};
