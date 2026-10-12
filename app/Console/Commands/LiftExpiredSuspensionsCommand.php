<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LiftExpiredSuspensionsCommand extends Command
{
    protected $signature = 'stylebite:lift-expired-suspensions';

    protected $description = 'Suspensions stay in place until an admin activates the account. This command exists so the server cron does not fail.';

    public function handle(): int
    {
        $this->info('No timed suspensions to lift. Suspended and banned accounts stay that way until an admin activates them.');

        return self::SUCCESS;
    }
}
