<?php

namespace App\Console\Commands;

use App\Support\ContestResults;
use Illuminate\Console\Command;

class ReportMissingContestPrizeCreditsCommand extends Command
{
    protected $signature = 'contests:missing-prize-credits
                            {--user= : Limit the report to one winner user id}
                            {--execute : Credit the missing prizes after review (off by default)}';

    protected $description = 'List completed contest prizes that have no earnings ledger row; optionally create pending (non-withdrawable) prize credits after approval.';

    public function handle(): int
    {
        $userOption = $this->option('user');
        $userId = $userOption !== null && $userOption !== '' ? (int) $userOption : null;
        $rows = ContestResults::missingPrizeCredits($userId !== null && $userId > 0 ? $userId : null);

        if ($rows === []) {
            $this->info('No missing contest prize credits found.');

            return self::SUCCESS;
        }

        $this->table(
            ['contest_id', 'contest_title', 'place_id', 'place', 'user_id', 'amount'],
            array_map(fn (array $row) => [
                $row['contest_id'],
                $row['contest_title'],
                $row['place_id'],
                $row['place'],
                $row['user_id'],
                number_format($row['amount'], 2, '.', ''),
            ], $rows)
        );

        if (! $this->option('execute')) {
            $this->warn('Dry run only. Re-run with --execute after explicit approval to create PENDING (non-withdrawable) prize credits.');

            return self::SUCCESS;
        }

        $userIds = collect($rows)->pluck('user_id')->unique()->values();
        foreach ($userIds as $id) {
            ContestResults::creditCompletedPrizes((int) $id);
        }

        $remaining = ContestResults::missingPrizeCredits($userId !== null && $userId > 0 ? $userId : null);
        $this->info('Created pending prize credits for '.$userIds->count().' user(s). Remaining missing rows: '.count($remaining).'. Admin approval is still required before available balance increases.');

        return self::SUCCESS;
    }
}
