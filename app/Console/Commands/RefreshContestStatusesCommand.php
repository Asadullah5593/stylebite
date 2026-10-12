<?php

namespace App\Console\Commands;

use App\Models\Contest;
use App\Models\ContestSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RefreshContestStatusesCommand extends Command
{
    protected $signature = 'stylebite:refresh-contest-statuses';

    protected $description = 'Move contests to active when they start and to completed when voting or the contest end has passed.';

    public function handle(): int
    {
        $now = now();
        $completed = 0;
        $activated = 0;

        Contest::query()
            ->whereIn('status', ['upcoming', 'active'])
            ->orderBy('id')
            ->each(function (Contest $contest) use ($now, &$completed, &$activated): void {
                if ($this->deadlineHasPassed($contest, $now)) {
                    $this->completeContest($contest, $now);
                    $completed++;

                    return;
                }

                if ($contest->status === 'upcoming' && $contest->start_at !== null && $contest->start_at->lte($now)) {
                    $contest->forceFill(['status' => 'active'])->save();
                    $activated++;
                }
            });

        $this->info("Activated {$activated} contest(s) and completed {$completed} contest(s).");

        return self::SUCCESS;
    }

    private function deadlineHasPassed(Contest $contest, Carbon $now): bool
    {
        $deadline = $contest->voting_end_at ?? $contest->end_at;

        return $deadline !== null && $now->gt($deadline);
    }

    private function completeContest(Contest $contest, Carbon $now): void
    {
        $winnerUserId = $contest->winner_user_id;

        if ($winnerUserId === null) {
            $winnerUserId = ContestSubmission::query()
                ->where('contest_id', $contest->id)
                ->where('submission_status', 'approved')
                ->orderByDesc('final_score')
                ->orderBy('id')
                ->value('user_id');
        }

        $contest->forceFill([
            'status' => 'completed',
            'result_at' => $contest->result_at ?? $now,
            'winner_user_id' => $winnerUserId,
        ])->save();
    }
}
