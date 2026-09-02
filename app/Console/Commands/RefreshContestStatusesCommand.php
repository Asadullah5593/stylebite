<?php

namespace App\Console\Commands;

use App\Services\ContestStatusResolver;
use Illuminate\Console\Command;

class RefreshContestStatusesCommand extends Command
{
    protected $signature = 'stylebite:refresh-contest-statuses';

    protected $description = 'Advance contest statuses to match their start and end dates (upcoming -> active -> completed).';

    public function handle(ContestStatusResolver $resolver): int
    {
        $result = $resolver->refreshAll();

        $this->info("Activated: {$result['activated']}  Completed: {$result['completed']}");

        return self::SUCCESS;
    }
}
