<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\NotificationAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers one notification campaign, a chunk at a time.
 *
 * Why chunked-and-requeued rather than one long job: this host has no
 * supervised worker. `queue:work --stop-when-empty --max-time=50` runs from a
 * per-minute cron, so any job that cannot finish in ~50 seconds would be killed
 * mid-flight. Each run therefore processes a bounded chunk, advances a keyset
 * cursor on the campaign row, and re-dispatches itself. A campaign of any size
 * makes forward progress every cron tick and survives the worker dying.
 *
 * Nothing here fans out inside an HTTP request — that was the original defect.
 */
class ProcessNotificationCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(NotificationAudience $audience): void
    {
        $campaign = NotificationCampaign::find($this->campaignId);

        if (! $campaign || $campaign->isFinished()) {
            return;
        }

        // One worker per campaign. Without this, two overlapping cron ticks
        // could both claim the same cursor and double-send that chunk.
        $lock = Cache::lock('notification-campaign-'.$campaign->id, 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->start($campaign, $audience);

            $chunkSize = max(1, (int) config('notifications.campaign_chunk_size', 200));

            $recipients = $audience->query($campaign->audience_type, $campaign->audience_payload ?? [])
                ->when($campaign->last_user_id, fn ($query) => $query->where('users.id', '>', $campaign->last_user_id))
                ->orderBy('users.id')
                ->limit($chunkSize)
                ->get(['users.id']);

            if ($recipients->isEmpty()) {
                $this->finish($campaign);

                return;
            }

            $sent = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($recipients as $recipient) {
                // Campaigns are sent as the system, not as the admin: passing an
                // actor equal to the recipient would make stylebite_notify_user
                // skip that person, so an admin in their own audience silently
                // received nothing. Who pressed send lives on the campaign row
                // and in the activity log.
                // Deliberately the same payload shape admin announcements have
                // always produced ('system'/'system', no entity id): the mobile
                // app switches on entity_type, so a campaign must not introduce
                // a value it has never seen. Campaign attribution lives on the
                // campaign row and its counters, not on each notification.
                $notification = stylebite_notify_user(
                    (int) $recipient->id,
                    null,
                    'system',
                    'system',
                    null,
                    $campaign->title,
                    $campaign->body,
                    $campaign->action_url,
                    $campaign->image_url
                );

                match ($notification->delivery_status) {
                    'sent' => $sent++,
                    'skipped' => $skipped++,
                    default => $failed++,
                };
            }

            $campaign->forceFill([
                'last_user_id' => (int) $recipients->last()->id,
                'processed_count' => $campaign->processed_count + $recipients->count(),
                'sent_count' => $campaign->sent_count + $sent,
                'skipped_count' => $campaign->skipped_count + $skipped,
                'failed_count' => $campaign->failed_count + $failed,
            ])->save();

            // Short of a full chunk means the audience is exhausted.
            if ($recipients->count() < $chunkSize) {
                $this->finish($campaign);

                return;
            }

            static::dispatch($campaign->id);
        } catch (Throwable $exception) {
            $campaign->forceFill([
                'status' => 'failed',
                'failure_reason' => Str::limit($exception->getMessage(), 500, ''),
                'completed_at' => now(),
            ])->save();

            Log::error('Notification campaign failed.', [
                'campaign_id' => $campaign->id,
                'processed' => $campaign->processed_count,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * Snapshot the recipient count on the first run so progress stays stable
     * even as the underlying audience shifts during a long send.
     */
    private function start(NotificationCampaign $campaign, NotificationAudience $audience): void
    {
        if ($campaign->status !== 'pending') {
            return;
        }

        $campaign->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'total_recipients' => $audience->query($campaign->audience_type, $campaign->audience_payload ?? [])->count(),
        ])->save();
    }

    private function finish(NotificationCampaign $campaign): void
    {
        $campaign->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            // The snapshot can drift from reality on a long send; report what
            // actually happened rather than a total that no longer holds.
            'total_recipients' => max($campaign->total_recipients, $campaign->processed_count),
        ])->save();
    }
}
