<?php

namespace App\Console\Commands;

use App\Jobs\OptimizePostMedia;
use App\Models\PostMedia;
use Illuminate\Console\Command;

/**
 * Backfills mobile-optimized renditions for post media that predate the
 * optimization pipeline (or whose optimization previously failed).
 *
 * By default it dispatches OptimizePostMedia to the queue; pass --sync to
 * process inline (handy for one-off runs without a worker).
 */
class OptimizeMediaCommand extends Command
{
    protected $signature = 'stylebite:optimize-media
        {--force : Re-optimize media that already has a rendition}
        {--sync : Process inline instead of dispatching to the queue}
        {--type= : Only image or video — a settings change to one need not re-transcode the other}
        {--chunk=200 : Rows to load per batch}';

    protected $description = 'Generate mobile-optimized renditions for existing post media (compressed images, <=720p video).';

    public function handle(): int
    {
        $query = PostMedia::query()->whereNotNull('file_path');

        $force = (bool) $this->option('force');

        if (! $force) {
            $query->whereNull('optimized_url');
        }

        $type = $this->option('type');

        if ($type !== null) {
            if (! in_array($type, ['image', 'video'], true)) {
                $this->error('--type must be image or video.');

                return self::FAILURE;
            }

            $query->where('media_type', $type);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No media needs optimization.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $this->info(($sync ? 'Processing' : 'Queueing').' '.$total.' media item(s)...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $processed = 0;

        // The job has its own "already optimized" guard, so --force has to travel
        // with each dispatch — widening the query alone re-dispatched every row
        // and then every row returned untouched.
        $query->orderBy('id')->chunkById((int) $this->option('chunk'), function ($mediaItems) use ($sync, $force, $bar, &$processed) {
            foreach ($mediaItems as $media) {
                $sync
                    ? OptimizePostMedia::dispatchSync($media->id, $force)
                    : OptimizePostMedia::dispatch($media->id, $force);

                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(($sync ? 'Optimized ' : 'Queued ').$processed.' media item(s).');

        return self::SUCCESS;
    }
}
