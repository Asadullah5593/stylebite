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
use App\Models\Profile;
use App\Models\PushNotificationLog;
use App\Models\Report;
use App\Models\WithdrawalRequest;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
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
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

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

        View::composer('admin.partials.sidebar', function ($view) {
            $view->with('sidebarCounts', once(function () {
                $userTabs = UserController::tabCounts();
                $socialTabs = SocialController::tabCounts();
                $postTabs = PostController::tabCounts();
                $commentTabs = CommentController::tabCounts();
                $engagementTabs = EngagementController::tabCounts();
                $mediaTabs = MediaController::tabCounts();
                $memoryTabs = MemoryController::tabCounts();
                $messagingTabs = MessagingController::tabCounts();
                $notificationTabs = NotificationController::tabCounts();
                $contestTabs = ContestController::tabCounts();
                $earningsTabs = EarningsController::tabCounts();

                return [
                    'users' => $userTabs['all_users'] ?? 0,
                    'verification_requests' => $userTabs['verification_requests'] ?? 0,
                    'monetization_requests' => $userTabs['monetization_requests'] ?? 0,
                    'social' => ($socialTabs['follows'] ?? 0) + ($socialTabs['blocks'] ?? 0),
                    'posts' => $postTabs['all_posts'] ?? 0,
                    'comments' => $commentTabs['comments'] ?? 0,
                    'engagement' => ($engagementTabs['post_likes'] ?? 0)
                        + ($engagementTabs['shares'] ?? 0)
                        + ($engagementTabs['saved'] ?? 0),
                    'media' => $mediaTabs['uploads'] ?? 0,
                    'memories' => $memoryTabs['memories'] ?? 0,
                    'messaging' => $messagingTabs['conversations'] ?? 0,
                    'notifications' => $notificationTabs['notifications'] ?? 0,
                    'moderation' => Report::query()
                        ->whereIn('status', ['open', 'under_review'])
                        ->count(),
                    'contests' => $contestTabs['contests'] ?? 0,
                    'contest_prizes' => $earningsTabs['contest_prizes'] ?? 0,
                    'earnings' => $earningsTabs['wallets'] ?? 0,
                    'settings' => SettingsController::tabCounts()['configs'] ?? 0,
                ];
            }));
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
                    'count' => Report::whereIn('status', ['open', 'under_review'])->count(),
                    'title' => 'open reports awaiting review',
                    'time' => 'Moderation queue',
                    'route' => route('admin.moderation.reports', ['status' => 'open']),
                    'kind' => 'ops',
                ],
                [
                    'count' => Profile::where('verification_status', 'pending')->count(),
                    'title' => 'verification requests awaiting review',
                    'time' => 'Users queue',
                    'route' => route('admin.users.verification_requests', ['status' => 'pending']),
                    'kind' => 'ops',
                ],
                [
                    'count' => Profile::where('monetization_status', 'pending')->count(),
                    'title' => 'monetization requests awaiting review',
                    'time' => 'Monetization queue',
                    'route' => route('admin.users.monetization_requests', ['status' => 'pending']),
                    'kind' => 'ops',
                ],
                [
                    'count' => EarningsController::tabCounts()['contest_prizes'] ?? 0,
                    'title' => 'contest prizes awaiting release',
                    'time' => 'Finance queue',
                    'route' => route('admin.earnings.contest_prizes', ['status' => 'pending']),
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
                ->take(5)
                ->values();

            $view->with('headerNotifications', $headerNotifications);
            $view->with('headerNotificationCount', $headerNotifications->count());
        });
    }
}
