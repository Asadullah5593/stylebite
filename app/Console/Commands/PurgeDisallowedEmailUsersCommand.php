<?php

namespace App\Console\Commands;

use App\Support\DisallowedEmailAccountPurge;
use Illuminate\Console\Command;

/**
 * Remove Yahoo / Outlook / temp-mail / etc. accounts and everything they did.
 *
 * Default is a dry-run report. Pass --force to permanently delete.
 */
class PurgeDisallowedEmailUsersCommand extends Command
{
    protected $signature = 'stylebite:purge-disallowed-emails
        {--force : Permanently delete matching users and all their content/actions}
        {--limit=50 : Max rows to list in the preview table}';

    protected $description = 'Purge users whose email is not Gmail/iCloud (and all posts, likes, votes, etc.).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $limit = max(1, (int) $this->option('limit'));

        $preview = DisallowedEmailAccountPurge::preview($limit);
        $total = DisallowedEmailAccountPurge::disallowedUsersQuery()->count();

        $this->info('Allowed domains: '.implode(', ', stylebite_allowed_email_domains()));
        $this->info("Matching users (non-admin/moderator): {$total}");

        if ($preview->isEmpty()) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'email', 'username', 'status', 'trashed'],
            $preview->map(fn ($user) => [
                $user->id,
                $user->email,
                $user->username,
                $user->status,
                $user->trashed() ? 'yes' : 'no',
            ])->all()
        );

        if ($total > $preview->count()) {
            $this->comment('… and '.($total - $preview->count()).' more not shown in the table.');
        }

        if (! $force) {
            $this->warn('Dry-run only. Re-run with --force to permanently delete these accounts and their actions.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Permanently delete {$total} account(s) and all of their posts/likes/votes/comments?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $stats = DisallowedEmailAccountPurge::purgeAll(dryRun: false);

        $this->info(sprintf(
            'Purged %d users, %d posts/reels, %d likes, %d contest votes.',
            $stats['users'],
            $stats['posts'],
            $stats['likes'],
            $stats['votes']
        ));

        return self::SUCCESS;
    }
}
