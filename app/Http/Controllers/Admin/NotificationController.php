<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNotificationCampaign;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\NotificationCampaign;
use App\Models\PushNotificationLog;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\NotificationAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function notifications(Request $request): View
    {
        $notifications = Notification::query()
            ->with([
                'recipient:id,username,full_name',
                'actor:id,username,full_name',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhereHas('recipient', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('actor', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('delivery_status'), fn ($query) => $query->where('delivery_status', $request->string('delivery_status')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $recipientOptions = User::query()
            ->where('status', 'active')
            ->orderBy('full_name')
            ->orderBy('username')
            ->limit(200)
            ->get(['id', 'username', 'full_name', 'email']);

        // Only offer cities users have actually set, so the picker can never
        // produce a guaranteed-empty audience.
        $cityOptions = \App\Models\Profile::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        return view('admin.notifications.NotificationsPage', compact('notifications', 'recipientOptions', 'cityOptions'));
    }

    /**
     * Create a campaign and hand it to the queue.
     *
     * This used to loop the entire audience inside the request, one FCM call
     * per device — fine for a demo, impossible at real scale, and it reported a
     * "sent" count that included recipients the delivery layer had skipped.
     * Now the request only records intent; delivery happens in a chunked,
     * resumable job.
     */
    public function sendAnnouncement(Request $request, NotificationAudience $audience): RedirectResponse
    {
        $data = $request->validate(array_merge($audience->validationRules(), [
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:500'],
            'action_url' => ['nullable', 'string', 'max:1024'],
            'image_url' => ['nullable', 'string', 'max:1024'],
        ]), [
            'audience_type.in' => 'Please choose a valid audience.',
            'cities.required_if' => 'Please choose at least one city.',
            'user_ids.required_if' => 'Please choose at least one user.',
            'user_ids.max' => 'A specific-user campaign is limited to 5000 recipients.',
        ]);

        $type = $data['audience_type'];
        $payload = $audience->payloadFor($type, $data);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => auth()->id(),
            'audience_type' => $type,
            'audience_payload' => $payload ?: null,
            'audience_label' => $audience->label($type, $payload),
            'title' => $data['title'],
            'body' => $data['body'],
            'action_url' => filled($data['action_url'] ?? null) ? $data['action_url'] : null,
            'image_url' => filled($data['image_url'] ?? null) ? $data['image_url'] : null,
            'status' => 'pending',
        ]);

        ProcessNotificationCampaign::dispatch($campaign->id);

        $this->logActivity('notification_campaign_created', 'notification_campaign', $campaign->id, [
            'audience_type' => $type,
            'audience_label' => $campaign->audience_label,
            'title' => $campaign->title,
        ]);

        return back()->with('status', "Campaign #{$campaign->id} queued for {$campaign->audience_label}. Delivery progress is on the Campaigns tab.");
    }

    /**
     * Live recipient count for the sender form, so an admin sees the size of an
     * audience before committing to it — a segment can legitimately be empty
     * (few users have a city set) and that should be visible, not a surprise.
     */
    public function previewAudience(Request $request, NotificationAudience $audience): JsonResponse
    {
        $data = $request->validate($audience->validationRules());

        $type = $data['audience_type'];
        $payload = $audience->payloadFor($type, $data);

        return response()->json([
            'label' => $audience->label($type, $payload),
            'count' => $audience->query($type, $payload)->count(),
        ]);
    }

    public function campaigns(Request $request): View
    {
        $campaigns = NotificationCampaign::query()
            ->with('creator:id,username,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('audience_label', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.notifications.CampaignsPage', compact('campaigns'));
    }

    /**
     * Stop a campaign that is still working through its audience. Recipients
     * already delivered to keep their notification; the rest are never sent.
     */
    public function cancelCampaign(NotificationCampaign $campaign): RedirectResponse
    {
        if ($campaign->isFinished()) {
            return back()->with('status', "Campaign #{$campaign->id} has already finished.");
        }

        $campaign->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
        ])->save();

        $this->logActivity('notification_campaign_cancelled', 'notification_campaign', $campaign->id, [
            'processed_count' => $campaign->processed_count,
            'total_recipients' => $campaign->total_recipients,
        ]);

        return back()->with('status', "Campaign #{$campaign->id} cancelled after {$campaign->processed_count} recipient(s).");
    }

    public function pushLogs(Request $request): View
    {
        $pushLogs = PushNotificationLog::query()
            ->with([
                'notification:id,title,type,delivery_status',
                'user:id,username,full_name',
                'deviceToken:id,device_id,platform,is_active',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('provider', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('provider_response', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('notification', fn ($query) => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('provider'), fn ($query) => $query->where('provider', $request->string('provider')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.notifications.PushLogsPage', compact('pushLogs'));
    }

    public function savedSearches(Request $request): View
    {
        $savedSearches = SavedSearch::query()
            ->with('user:id,username,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('query', 'like', "%{$search}%")
                        ->orWhere('result_scope', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('result_scope'), fn ($query) => $query->where('result_scope', $request->string('result_scope')))
            ->latest('last_used_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.notifications.SavedSearchesPage', compact('savedSearches'));
    }

    /**
     * Re-send one failed push, for real.
     *
     * This previously wrote a `queued` log row that nothing consumed, told the
     * admin it had been queued, and reset the notification's delivery_status to
     * `pending` — overwriting the very delivery record it was meant to repair.
     * Now it performs the send and reports the actual provider outcome.
     */
    public function retryPushLog(PushNotificationLog $pushLog): RedirectResponse
    {
        $pushLog->load(['notification', 'deviceToken']);

        if (! $pushLog->notification_id || ! $pushLog->user_id || ! $pushLog->notification) {
            return back()->with('status', 'This push cannot be retried: the notification or user it belonged to no longer exists.');
        }

        $deviceToken = $pushLog->deviceToken;

        if (! $deviceToken || ! $deviceToken->push_token) {
            return back()->with('status', 'This push cannot be retried: the device it was sent to no longer has a registered token.');
        }

        if (! $deviceToken->is_active) {
            return back()->with('status', 'This push cannot be retried: the device is disabled. Re-enable it first.');
        }

        $notification = $pushLog->notification;

        try {
            $result = stylebite_send_firebase_push_batch(
                [$deviceToken->push_token],
                $notification->title,
                $notification->body,
                [
                    'notification_id' => (string) $notification->id,
                    'type' => (string) $notification->type,
                    'entity_type' => (string) $notification->entity_type,
                    'entity_id' => $notification->entity_id !== null ? (string) $notification->entity_id : '',
                    'recipient_user_id' => (string) $notification->recipient_user_id,
                    'action_url' => $notification->action_url ?? '',
                ],
                stylebite_asset_url($notification->image_url)
            )[0] ?? ['status' => 'failed', 'provider_response' => 'No provider response.', 'sent_at' => null];
        } catch (\Throwable $exception) {
            $result = [
                'status' => 'failed',
                'provider_response' => \Illuminate\Support\Str::limit($exception->getMessage(), 65000, ''),
                'sent_at' => null,
            ];
        }

        $retryLog = PushNotificationLog::create([
            'notification_id' => $pushLog->notification_id,
            'user_id' => $pushLog->user_id,
            'device_token_id' => $deviceToken->id,
            'provider' => 'fcm',
            'status' => $result['status'],
            'provider_response' => $result['provider_response'],
            'sent_at' => $result['sent_at'],
            'created_at' => now(),
        ]);

        // Only ever improve the notification's recorded state. A failed retry on
        // one device must not mark a notification that reached another device as
        // failed, and nothing here may reset it to pending.
        if ($result['status'] === 'sent') {
            $notification->forceFill([
                'delivery_status' => 'sent',
                'push_sent_at' => $result['sent_at'],
            ])->save();
        } elseif (in_array($notification->delivery_status, ['pending', 'skipped'], true)) {
            $notification->forceFill(['delivery_status' => 'failed'])->save();
        }

        $this->logActivity('push_log_retried', 'push_notification_log', $pushLog->id, [
            'retry_log_id' => $retryLog->id,
            'notification_id' => $pushLog->notification_id,
            'user_id' => $pushLog->user_id,
            'provider' => 'fcm',
            'result' => $result['status'],
        ]);

        return back()->with('status', $result['status'] === 'sent'
            ? "Push re-sent successfully to device #{$deviceToken->id}."
            : "Retry failed — the provider rejected the send. See push log #{$retryLog->id} for the response.");
    }

    private function logActivity(string $eventName, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function tabCounts(): array
    {
        return [
            'notifications' => Notification::count(),
            'campaigns' => NotificationCampaign::count(),
            'push_logs' => PushNotificationLog::count(),
            'saved_searches' => SavedSearch::count(),
        ];
    }
}
