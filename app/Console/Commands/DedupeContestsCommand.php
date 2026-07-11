<?php

namespace App\Console\Commands;

use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\ContestSubmission;
use App\Models\ContestVote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Finds admin contests that share the same title (duplicates created before
 * double-submit protection existed) and soft-deletes the redundant copies.
 *
 * Safety rules:
 * - Runs as a dry-run REPORT by default; nothing is deleted without --force.
 * - Within a duplicate group, the contest with the most activity
 *   (participants + submissions + votes) is kept; ties keep the oldest id.
 * - A redundant copy is only deleted when it has ZERO activity; copies with
 *   any participants/submissions/votes are flagged for manual review instead.
 */
class DedupeContestsCommand extends Command
{
    protected $signature = 'stylebite:dedupe-contests
        {--force : Actually soft-delete redundant empty duplicates (default is a dry-run report)}';

    protected $description = 'Report (and with --force, soft-delete) duplicate admin contests that share the same title.';

    public function handle(): int
    {
        $duplicateTitles = Contest::query()
            ->where('category', 'admin')
            ->selectRaw('LOWER(title) as normalized_title')
            ->groupBy('normalized_title')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_title');

        if ($duplicateTitles->isEmpty()) {
            $this->info('No duplicate admin contest titles found.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $deleted = 0;
        $needsReview = 0;

        foreach ($duplicateTitles as $title) {
            $group = Contest::query()
                ->where('category', 'admin')
                ->whereRaw('LOWER(title) = ?', [$title])
                ->orderBy('id')
                ->get()
                ->map(function (Contest $contest) {
                    $contest->activity_count =
                        ContestParticipant::query()->where('contest_id', $contest->id)->count()
                        + ContestSubmission::query()->where('contest_id', $contest->id)->count()
                        + ContestVote::query()->where('contest_id', $contest->id)->count();

                    return $contest;
                });

            $keep = $group
                ->sortBy([['activity_count', 'desc'], ['id', 'asc']])
                ->first();

            $this->newLine();
            $this->line('Title: "'.$group->first()->title.'" — '.$group->count().' copies');
            $this->line('  KEEP   id='.$keep->id.' (activity: '.$keep->activity_count.', created: '.$keep->created_at.')');

            foreach ($group as $contest) {
                if ($contest->id === $keep->id) {
                    continue;
                }

                if ($contest->activity_count > 0) {
                    $needsReview++;
                    $this->warn('  REVIEW id='.$contest->id.' — has activity ('.$contest->activity_count.'), NOT deleting; merge manually.');

                    continue;
                }

                if ($force) {
                    $contest->delete();
                    $deleted++;
                    $this->info('  DELETED id='.$contest->id.' (soft-deleted, no activity)');
                } else {
                    $this->line('  WOULD DELETE id='.$contest->id.' (no activity) — run with --force to apply');
                }
            }
        }

        $this->newLine();
        $this->info($force
            ? "Done. Soft-deleted {$deleted} duplicate(s); {$needsReview} flagged for manual review."
            : 'Dry-run only — nothing deleted. Re-run with --force to apply.');

        return self::SUCCESS;
    }
}
