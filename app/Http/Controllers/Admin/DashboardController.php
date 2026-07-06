<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Contest;
use App\Models\EarningsWallet;
use App\Models\EarningTransaction;
use App\Models\MediaUpload;
use App\Models\Memory;
use App\Models\Post;
use App\Models\PushNotificationLog;
use App\Models\Report;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now = CarbonImmutable::now();
        $periodStart = $now->subDays(13)->startOfDay();
        $previousStart = $periodStart->subDays(14);

        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'active')->count(),
            'totalPosts' => Post::count(),
            'publishedPosts' => Post::where('status', 'published')->count(),
            'totalMemories' => Memory::count(),
            'favoriteMemories' => Memory::where('is_favorite', true)->count(),
            'activeContests' => Contest::where('status', 'active')->count(),
            'openReports' => Report::whereIn('status', ['open', 'under_review'])->count(),
            'pendingWithdrawals' => WithdrawalRequest::whereIn('status', ['pending', 'processing'])->count(),
            'failedPushes' => PushNotificationLog::where('status', 'failed')->where('created_at', '>=', $now->subDay())->count(),
            'totalBalance' => (float) EarningsWallet::sum('available_balance'),
        ];

        $deltas = [
            'users' => $this->periodDelta(User::class, $periodStart, $previousStart),
            'posts' => $this->periodDelta(Post::class, $periodStart, $previousStart),
            'memories' => $this->periodDelta(Memory::class, $periodStart, $previousStart),
            'contests' => $this->periodDelta(
                Contest::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->where('status', 'active')
            ),
            'reports' => $this->periodDelta(
                Report::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->whereIn('status', ['open', 'under_review'])
            ),
            'balance' => 0,
        ];

        $growth = $this->growthData($periodStart);
        $mediaByType = $this->mediaByType();
        $earningsOverview = $this->earningsOverview($now);
        $reportReasons = $this->reportReasons();
        $statusSnapshots = $this->statusSnapshots($stats);
        $recentReports = $this->recentReports();
        $withdrawalQueue = $this->withdrawalQueue();
        $recentUsers = $this->recentUsers();
        $recentActivity = $this->recentActivity();
        $actionCards = $this->actionCards($now);

        $statCards = [
            ['label' => 'Total Users', 'value' => number_format($stats['totalUsers']), 'sub' => $stats['activeUsers'].' active', 'delta' => $deltas['users'], 'icon' => 'bi-people', 'accent' => 'primary'],
            ['label' => 'Posts', 'value' => number_format($stats['totalPosts']), 'sub' => $stats['publishedPosts'].' published', 'delta' => $deltas['posts'], 'icon' => 'bi-file-earmark-text', 'accent' => 'info'],
            ['label' => 'Memories', 'value' => number_format($stats['totalMemories']), 'sub' => $stats['favoriteMemories'].' favorites', 'delta' => $deltas['memories'], 'icon' => 'bi-images', 'accent' => 'primary'],
            ['label' => 'Active Contests', 'value' => number_format($stats['activeContests']), 'sub' => '', 'delta' => $deltas['contests'], 'icon' => 'bi-trophy', 'accent' => 'warning'],
            ['label' => 'Open Reports', 'value' => number_format($stats['openReports']), 'sub' => 'Needs moderation follow-up', 'delta' => $deltas['reports'], 'icon' => 'bi-shield-exclamation', 'accent' => 'danger'],
            ['label' => 'Total Balance', 'value' => '$'.number_format($stats['totalBalance'], 0), 'sub' => '', 'delta' => $deltas['balance'], 'icon' => 'bi-wallet2', 'accent' => 'success'],
        ];

        return view('admin.dashboard', compact(
            'actionCards',
            'statCards',
            'growth',
            'mediaByType',
            'earningsOverview',
            'reportReasons',
            'statusSnapshots',
            'recentReports',
            'withdrawalQueue',
            'recentUsers',
            'recentActivity'
        ));
    }

    private function periodDelta(string $model, CarbonImmutable $periodStart, CarbonImmutable $previousStart, ?callable $scope = null): int
    {
        $currentQuery = $model::query()->where('created_at', '>=', $periodStart);
        $previousQuery = $model::query()->whereBetween('created_at', [$previousStart, $periodStart->subSecond()]);

        if ($scope !== null) {
            $currentQuery = $scope($currentQuery);
            $previousQuery = $scope($previousQuery);
        }

        $current = $currentQuery->count();
        $previous = $previousQuery->count();

        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function growthData(CarbonImmutable $periodStart)
    {
        $dailyUsers = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', $periodStart)
            ->groupBy('date')
            ->pluck('total', 'date');

        $dailyPosts = Post::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', $periodStart)
            ->groupBy('date')
            ->pluck('total', 'date');

        return collect(range(0, 13))->map(function (int $offset) use ($periodStart, $dailyUsers, $dailyPosts) {
            $date = $periodStart->addDays($offset);
            $key = $date->toDateString();

            return [
                'date' => $date->format('M j'),
                'users' => (int) ($dailyUsers[$key] ?? 0),
                'posts' => (int) ($dailyPosts[$key] ?? 0),
            ];
        });
    }

    private function mediaByType()
    {
        return MediaUpload::query()
            ->selectRaw('upload_type as name, COUNT(*) as value')
            ->groupBy('upload_type')
            ->orderByDesc('value')
            ->limit(6)
            ->get()
            ->map(fn ($item) => [
                'name' => str($item->name)->replace('_', ' ')->title()->toString(),
                'value' => (int) $item->value,
            ]);
    }

    private function earningsOverview(CarbonImmutable $now)
    {
        $dailyEarnings = EarningTransaction::query()
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('transaction_type', 'credit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $now->subDays(6)->startOfDay())
            ->groupBy('date')
            ->pluck('total', 'date');

        $dailyWithdrawals = WithdrawalRequest::query()
            ->selectRaw('DATE(requested_at) as date, SUM(amount) as total')
            ->where('requested_at', '>=', $now->subDays(6)->startOfDay())
            ->groupBy('date')
            ->pluck('total', 'date');

        return collect(range(0, 6))->map(function (int $offset) use ($now, $dailyEarnings, $dailyWithdrawals) {
            $date = $now->subDays(6 - $offset);
            $key = $date->toDateString();

            return [
                'date' => $date->format('D'),
                'earnings' => (float) ($dailyEarnings[$key] ?? 0),
                'withdrawals' => (float) ($dailyWithdrawals[$key] ?? 0),
            ];
        });
    }

    private function reportReasons()
    {
        return Report::query()
            ->selectRaw('reason, COUNT(*) as total')
            ->whereNotNull('reason')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('reason')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($item) => [
                'name' => str($item->reason ?: 'Unknown')->replace('_', ' ')->title()->toString(),
                'value' => (int) $item->total,
            ]);
    }

    private function statusSnapshots(array $stats): array
    {
        return [
            [
                'label' => 'Pending Withdrawals',
                'value' => number_format($stats['pendingWithdrawals']),
                'hint' => 'Finance queue awaiting action',
                'route' => route('admin.earnings.withdrawals'),
                'icon' => 'bi-cash-stack',
            ],
            [
                'label' => 'Failed Pushes',
                'value' => number_format($stats['failedPushes']),
                'hint' => 'Delivery failures in the last 24 hours',
                'route' => route('admin.notifications.push_logs'),
                'icon' => 'bi-bell-slash',
            ],
            [
                'label' => 'Open Reports',
                'value' => number_format($stats['openReports']),
                'hint' => 'Open or under review moderation items',
                'route' => route('admin.moderation.reports'),
                'icon' => 'bi-flag',
            ],
            [
                'label' => 'Banned Users',
                'value' => number_format(User::where('status', 'banned')->count()),
                'hint' => 'Restricted accounts currently in effect',
                'route' => route('admin.users.all_users', ['status' => 'banned']),
                'icon' => 'bi-person-lock',
            ],
        ];
    }

    private function recentReports()
    {
        return Report::query()
            ->with([
                'reporter:id,username,full_name',
                'reviewer:id,username,full_name',
            ])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'reason' => str($report->reason ?: 'Unknown')->replace('_', ' ')->title()->toString(),
                'target' => str($report->target_type ?: 'item')->replace('_', ' ')->title()->toString(),
                'status' => $report->status,
                'reporter' => $report->reporter?->full_name ?: ($report->reporter?->username ? '@'.$report->reporter->username : 'Unknown reporter'),
                'reviewer' => $report->reviewer?->full_name ?: ($report->reviewer?->username ? '@'.$report->reviewer->username : null),
                'time' => optional($report->created_at)->format('M j, H:i'),
            ]);
    }

    private function withdrawalQueue()
    {
        return WithdrawalRequest::query()
            ->with('user:id,username,full_name')
            ->whereIn('status', ['pending', 'processing'])
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->map(fn (WithdrawalRequest $withdrawal) => [
                'id' => $withdrawal->id,
                'user' => $withdrawal->user?->full_name ?: ($withdrawal->user?->username ? '@'.$withdrawal->user->username : 'Unknown user'),
                'amount' => '$'.number_format((float) $withdrawal->amount, 2),
                'method' => str($withdrawal->method ?: 'n/a')->replace('_', ' ')->title()->toString(),
                'status' => $withdrawal->status,
                'time' => optional($withdrawal->requested_at)->format('M j, H:i'),
            ]);
    }

    private function recentUsers()
    {
        return User::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->username,
                'username' => '@'.$user->username,
                'role' => str($user->role)->title()->toString(),
                'avatar' => $user->avatar_url,
            ]);
    }

    private function recentActivity()
    {
        return ActivityLog::query()
            ->with('user:id,username,full_name')
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (ActivityLog $entry) => [
                'action' => $entry->event_name,
                'meta' => collect([
                    $entry->user?->full_name ?: ($entry->user?->username ? '@'.$entry->user->username : null),
                    $entry->entity_type,
                    $entry->ip_address,
                ])->filter()->implode(' · '),
                'time' => optional($entry->created_at)->format('M j H:i'),
            ]);
    }

    private function actionCards(CarbonImmutable $now)
    {
        return collect([
            [
                'label' => 'Posts under review',
                'count' => Post::where('status', 'under_review')->count(),
                'urgency' => 'med',
                'hint' => 'Hidden until moderator decision',
                'icon' => 'bi-shield-check',
                'route' => route('admin.posts.all_posts'),
            ],
            [
                'label' => 'Pending withdrawals',
                'count' => WithdrawalRequest::whereIn('status', ['pending', 'processing'])->count(),
                'urgency' => 'med',
                'hint' => 'Approve or reject payouts',
                'icon' => 'bi-wallet2',
                'route' => route('admin.earnings.withdrawals'),
            ],
            [
                'label' => 'Failed pushes',
                'count' => PushNotificationLog::where('status', 'failed')->where('created_at', '>=', $now->subDay())->count(),
                'urgency' => 'low',
                'hint' => 'Last 24h delivery failures',
                'icon' => 'bi-bell-slash',
                'route' => route('admin.notifications.push_logs'),
            ],
            [
                'label' => 'Banned users',
                'count' => User::where('status', 'banned')->count(),
                'urgency' => 'low',
                'hint' => 'Currently restricted from app',
                'icon' => 'bi-person',
                'route' => route('admin.users.all_users', ['status' => 'banned']),
            ],
        ]);
    }
}
