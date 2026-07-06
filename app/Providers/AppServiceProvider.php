<?php

namespace App\Providers;

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\MemoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MessagingController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SocialController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ContestController;
use App\Http\Controllers\Admin\EarningsController;
use App\Http\Controllers\Admin\EngagementController;
use App\Models\Notification;
use App\Models\PushNotificationLog;
use App\Models\Report;
use App\Models\WithdrawalRequest;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('admin.users.*', function ($view) {
            $view->with('userTabCounts', UserController::tabCounts());
        });

        View::composer('admin.posts.*', function ($view) {
            $view->with('postTabCounts', PostController::tabCounts());
        });

        View::composer('admin.comments.*', function ($view) {
            $view->with('commentTabCounts', CommentController::tabCounts());
        });

        View::composer('admin.memories.*', function ($view) {
            $view->with('memoryTabCounts', MemoryController::tabCounts());
        });

        View::composer('admin.media.*', function ($view) {
            $view->with('mediaTabCounts', MediaController::tabCounts());
        });

        View::composer('admin.social.*', function ($view) {
            $view->with('socialTabCounts', SocialController::tabCounts());
        });

        View::composer('admin.engagement.*', function ($view) {
            $view->with('engagementTabCounts', EngagementController::tabCounts());
        });

        View::composer('admin.moderation.*', function ($view) {
            $view->with('moderationTabCounts', ModerationController::tabCounts());
        });

        View::composer('admin.messaging.*', function ($view) {
            $view->with('messagingTabCounts', MessagingController::tabCounts());
        });

        View::composer('admin.notifications.*', function ($view) {
            $view->with('notificationTabCounts', NotificationController::tabCounts());
        });

        View::composer('admin.contests.*', function ($view) {
            $view->with('contestTabCounts', ContestController::tabCounts());
        });

        View::composer('admin.earnings.*', function ($view) {
            $view->with('earningsTabCounts', EarningsController::tabCounts());
        });

        View::composer('admin.settings.*', function ($view) {
            $view->with('settingsTabCounts', SettingsController::tabCounts());
        });

        View::composer('admin.partials.header', function ($view) {
            $adminUser = auth()->user();

            $personalNotifications = collect();

            if ($adminUser) {
                $personalNotifications = Notification::query()
                    ->where('recipient_user_id', $adminUser->id)
                    ->latest('created_at')
                    ->limit(4)
                    ->get()
                    ->map(function ($notification) {
                        return [
                            'title' => $notification->title ?: 'Notification',
                            'time' => $notification->created_at?->diffForHumans() ?? 'Recently',
                            'route' => route('admin.notifications.notifications', [
                                'q' => $notification->title ?: $notification->body,
                            ]),
                            'kind' => 'personal',
                        ];
                    });
            }

            $operationalAlerts = collect([
                [
                    'count' => Report::where('status', 'open')->count(),
                    'title' => 'open reports awaiting review',
                    'time' => 'Moderation queue',
                    'route' => route('admin.moderation.reports'),
                    'kind' => 'ops',
                ],
                [
                    'count' => WithdrawalRequest::whereIn('status', ['pending', 'processing'])->count(),
                    'title' => 'withdrawals pending action',
                    'time' => 'Finance queue',
                    'route' => route('admin.earnings.withdrawals'),
                    'kind' => 'ops',
                ],
                [
                    'count' => PushNotificationLog::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
                    'title' => 'failed push deliveries in last 24h',
                    'time' => 'Delivery health',
                    'route' => route('admin.notifications.push_logs'),
                    'kind' => 'ops',
                ],
            ])->filter(fn ($alert) => $alert['count'] > 0)
                ->map(fn ($alert) => [
                    'title' => number_format($alert['count']).' '.$alert['title'],
                    'time' => $alert['time'],
                    'route' => $alert['route'],
                    'kind' => $alert['kind'],
                ]);

            $headerNotifications = $personalNotifications
                ->concat($operationalAlerts)
                ->take(4)
                ->values();

            $view->with('headerNotifications', $headerNotifications);
            $view->with('headerNotificationCount', $headerNotifications->count());
        });
    }
}
