<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\PushNotificationLog;
use App\Models\SavedSearch;
use App\Models\User;
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

        return view('admin.notifications.NotificationsPage', compact('notifications', 'recipientOptions'));
    }

    public function sendAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_scope' => ['required', 'in:single,all_active'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:500'],
            'action_url' => ['nullable', 'string', 'max:1024'],
            'image_url' => ['nullable', 'string', 'max:1024'],
        ]);

        if ($data['recipient_scope'] === 'single' && empty($data['recipient_user_id'])) {
            return back()
                ->withErrors(['recipient_user_id' => 'Please choose an active user for a single-user announcement.'])
                ->withInput();
        }

        $query = User::query()
            ->where('status', 'active');

        if ($data['recipient_scope'] === 'single') {
            $query->whereKey($data['recipient_user_id']);
        }

        $recipients = $query->get(['id']);

        $sentCount = 0;

        foreach ($recipients as $recipient) {
            stylebite_notify_user(
                $recipient->id,
                auth()->id(),
                'system',
                'system',
                null,
                $data['title'],
                $data['body'],
                filled($data['action_url']) ? $data['action_url'] : null,
                filled($data['image_url']) ? $data['image_url'] : null
            );

            $sentCount++;
        }

        $this->logActivity('system_notification_sent', 'notification', null, [
            'recipient_scope' => $data['recipient_scope'],
            'recipient_user_id' => $data['recipient_user_id'] ?? null,
            'title' => $data['title'],
            'sent_count' => $sentCount,
        ]);

        return back()->with('status', "System notification queued for {$sentCount} recipient(s).");
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

    public function retryPushLog(PushNotificationLog $pushLog): RedirectResponse
    {
        if (! $pushLog->notification_id || ! $pushLog->user_id) {
            return back()->with('status', 'Push log cannot be retried because notification or user context is missing.');
        }

        $retryLog = PushNotificationLog::create([
            'notification_id' => $pushLog->notification_id,
            'user_id' => $pushLog->user_id,
            'device_token_id' => $pushLog->device_token_id,
            'provider' => $pushLog->provider,
            'status' => 'queued',
            'provider_response' => 'Queued for retry by admin on '.now()->toDateTimeString().'.',
            'sent_at' => null,
            'created_at' => now(),
        ]);

        if ($pushLog->notification) {
            $pushLog->notification->delivery_status = 'pending';
            $pushLog->notification->push_sent_at = null;
            $pushLog->notification->save();
        }

        $this->logActivity('push_log_retried', 'push_notification_log', $pushLog->id, [
            'retry_log_id' => $retryLog->id,
            'notification_id' => $pushLog->notification_id,
            'user_id' => $pushLog->user_id,
            'provider' => $pushLog->provider,
        ]);

        return back()->with('status', 'Push log queued for retry.');
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
            'push_logs' => PushNotificationLog::count(),
            'saved_searches' => SavedSearch::count(),
        ];
    }
}
