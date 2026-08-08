<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\Contest;
use App\Models\EarningsWallet;
use App\Models\EarningTransaction;
use App\Models\MediaUpload;
use App\Models\Memory;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\Profile;
use App\Models\PushNotificationLog;
use App\Models\ReplyLike;
use App\Models\Report;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
            'completedContests' => Contest::where('status', 'completed')->count(),
            'decidedContests' => Contest::where('status', 'completed')
                ->where(fn ($query) => $query->whereNotNull('winner_user_id')->orWhereNotNull('winner_team_id'))
                ->count(),
            'openReports' => Report::whereIn('status', ['open', 'under_review'])->count(),
            'pendingWithdrawals' => WithdrawalRequest::whereIn('status', ['pending', 'processing'])->count(),
            'failedPushes' => PushNotificationLog::where('status', 'failed')->where('created_at', '>=', $now->subDay())->count(),
            'totalBalance' => (float) EarningsWallet::sum('available_balance'),
        ] + $this->contentCounts();

        $deltas = [
            'users' => $this->periodDelta(User::class, $periodStart, $previousStart),
            'posts' => $this->periodDelta(Post::class, $periodStart, $previousStart),
            'reels' => $this->periodDelta(
                Post::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->where('media_kind', 'video')
            ),
            'foodReviews' => $this->periodDelta(
                Post::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->where('post_type', 'food')
            ),
            'memories' => $this->periodDelta(Memory::class, $periodStart, $previousStart),
            'contests' => $this->periodDelta(
                Contest::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->where('status', 'active')
            ),
            // Measured on end_at, not created_at: what matters is how many
            // contests finished this period, not how many were created.
            'completedContests' => $this->periodDelta(
                Contest::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->where('status', 'completed'),
                'end_at'
            ),
            'reports' => $this->periodDelta(
                Report::class,
                $periodStart,
                $previousStart,
                fn ($query) => $query->whereIn('status', ['open', 'under_review'])
            ),
            'balance' => 0,
        ];

        $audienceCards = $this->audienceCards($now);
        $engagementCards = $this->engagementCards($periodStart, $previousStart);
        ['posts' => $topPosts, 'reels' => $topReels] = $this->topContent();
        $streakCards = $this->streakCards();
        $payoutCards = $this->payoutCards($now);
        $growth = $this->growthData($periodStart);
        $mediaByType = $this->mediaByType();
        $earningsOverview = $this->earningsOverview($now);
        $reportReasons = $this->reportReasons();
        $recentReports = $this->recentReports();
        $withdrawalQueue = $this->withdrawalQueue();
        $recentUsers = $this->recentUsers();
        $recentActivity = $this->recentActivity();

        $bannedUsers = User::where('status', 'banned')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();
        $postsUnderReview = Post::where('status', 'under_review')->count();
        $actionCards = $this->actionCards($stats, $postsUnderReview, $bannedUsers);

        $statCards = [
            ['label' => 'Total Users', 'value' => number_format($stats['totalUsers']), 'sub' => $stats['activeUsers'].' active', 'delta' => $deltas['users'], 'icon' => 'bi-people', 'accent' => 'primary'],
            ['label' => 'Posts', 'value' => number_format($stats['totalPosts']), 'sub' => $stats['publishedPosts'].' published', 'delta' => $deltas['posts'], 'icon' => 'bi-file-earmark-text', 'accent' => 'info'],
            ['label' => 'Reels', 'value' => number_format($stats['totalReels']), 'sub' => $stats['publishedReels'].' published', 'delta' => $deltas['reels'], 'icon' => 'bi-camera-reels', 'accent' => 'info'],
            ['label' => 'Food Reviews', 'value' => number_format($stats['totalFoodReviews']), 'sub' => $stats['ratedFoodReviews'].' rated', 'delta' => $deltas['foodReviews'], 'icon' => 'bi-egg-fried', 'accent' => 'warning'],
            ['label' => 'Memories', 'value' => number_format($stats['totalMemories']), 'sub' => $stats['favoriteMemories'].' favorites', 'delta' => $deltas['memories'], 'icon' => 'bi-images', 'accent' => 'primary'],
            ['label' => 'Active Contests', 'value' => number_format($stats['activeContests']), 'sub' => '', 'delta' => $deltas['contests'], 'icon' => 'bi-trophy', 'accent' => 'warning'],
            ['label' => 'Completed Contests', 'value' => number_format($stats['completedContests']), 'sub' => $stats['decidedContests'].' with a winner', 'delta' => $deltas['completedContests'], 'icon' => 'bi-trophy-fill', 'accent' => 'success'],
            ['label' => 'Open Reports', 'value' => number_format($stats['openReports']), 'sub' => 'Needs moderation follow-up', 'delta' => $deltas['reports'], 'icon' => 'bi-shield-exclamation', 'accent' => 'danger'],
            ['label' => 'Total Balance', 'value' => '$'.number_format($stats['totalBalance'], 0), 'sub' => '', 'delta' => $deltas['balance'], 'icon' => 'bi-wallet2', 'accent' => 'success'],
        ];

        // The dashboard renders as five tabs; each tab re-groups the metric
        // cards built above. Cards are picked by label so a tile keeps its
        // value, sub line and delta wiring wherever it appears — a card may
        // show up in more than one tab (only one tab is visible at a time).
        $byLabel = collect($statCards)
            ->concat($audienceCards)
            ->concat($engagementCards)
            ->concat($streakCards)
            ->concat($payoutCards)
            ->keyBy('label');

        $tile = fn (string $label, ?string $route = null) => array_merge(
            $byLabel[$label],
            $route ? ['route' => $route] : []
        );

        // Formerly the "status snapshots" link tiles — their metric and link
        // now live directly on the matching tab tiles.
        $pendingWithdrawalsTile = [
            'label' => 'Pending Withdrawals',
            'value' => number_format($stats['pendingWithdrawals']),
            'sub' => 'Finance queue awaiting action',
            'delta' => null,
            'deltaLabel' => '',
            'route' => route('admin.earnings.withdrawals'),
        ];

        $failedPushesTile = [
            'label' => 'Failed Pushes',
            'value' => number_format($stats['failedPushes']),
            'sub' => 'Delivery failures in the last 24 hours',
            'delta' => null,
            'deltaLabel' => '',
            'route' => route('admin.notifications.push_logs'),
        ];

        $bannedUsersTile = [
            'label' => 'Banned Users',
            'value' => number_format($bannedUsers),
            'sub' => 'Restricted accounts currently in effect',
            'delta' => null,
            'deltaLabel' => '',
            'route' => route('admin.users.all_users', ['status' => 'banned']),
        ];

        $suspendedUsersTile = [
            'label' => 'Suspended Users',
            'value' => number_format($suspendedUsers),
            'sub' => 'Temporarily locked, lift automatically on expiry',
            'delta' => null,
            'deltaLabel' => '',
            'route' => route('admin.users.all_users', ['status' => 'suspended']),
        ];

        $underReviewTile = [
            'label' => 'Posts Under Review',
            'value' => number_format($postsUnderReview),
            'sub' => 'Hidden until moderator decision',
            'delta' => null,
            'deltaLabel' => '',
            'route' => route('admin.posts.all_posts'),
        ];

        $tabTiles = [
            'overview' => [
                $tile('Total Users'),
                $tile('Monthly Active Users'),
                $tile('Posts'),
                $tile('Total Balance'),
                $tile('Daily Active Users'),
                $tile('Reels'),
                $tile('Open Reports', route('admin.moderation.reports')),
                $tile('Pending Payouts', route('admin.earnings.withdrawals')),
            ],
            'audience' => [
                $tile('Daily Active Users'),
                $tile('Monthly Active Users'),
                $tile('New Signups'),
                $tile('Total Users', route('admin.users.all_users')),
                $tile('Active Streaks'),
                $tile('Longest Streak'),
                $tile('Average Streak'),
                $bannedUsersTile,
            ],
            'content' => [
                $tile('Posts', route('admin.posts.all_posts')),
                $tile('Reels'),
                $tile('Food Reviews'),
                $tile('Memories'),
                $tile('Likes'),
                $tile('Comments'),
                $tile('Shares'),
                $tile('Active Contests'),
                $tile('Completed Contests'),
            ],
            'money' => [
                $tile('Total Balance'),
                $tile('Pending Payouts', route('admin.earnings.withdrawals')),
                $tile('Completed Payouts'),
                $pendingWithdrawalsTile,
            ],
            'trust' => [
                $underReviewTile,
                $tile('Open Reports', route('admin.moderation.reports')),
                $bannedUsersTile,
                $suspendedUsersTile,
                $failedPushesTile,
            ],
        ];

        return view('admin.dashboard', compact(
            'actionCards',
            'tabTiles',
            'growth',
            'mediaByType',
            'earningsOverview',
            'reportReasons',
            'recentReports',
            'withdrawalQueue',
            'recentUsers',
            'recentActivity',
            'topPosts',
            'topReels'
        ));
    }

    /**
     * Creator payouts, pending against completed.
     *
     * Counts only, deliberately — no amounts. Every payout row carries its own
     * `currency_code`, so summing amounts across rows would add PKR to USD the
     * moment a second currency exists. A count says the same operational thing
     * ("four payouts are waiting on you") and cannot go wrong.
     *
     * Pending covers `pending` and `processing`, matching how the withdrawals
     * queue and the action cards already group them. Failed and rejected
     * payouts are in neither card — they are on the withdrawals page.
     */
    private function payoutCards(CarbonImmutable $now): array
    {
        $metrics = Cache::remember('admin_dashboard_payouts', 300, function () use ($now) {
            $monthStart = $now->subDays(29)->startOfDay();
            $previousMonthStart = $now->subDays(59)->startOfDay();

            return [
                'awaiting' => WithdrawalRequest::where('status', 'pending')->count(),
                'processing' => WithdrawalRequest::where('status', 'processing')->count(),
                'completed' => WithdrawalRequest::where('status', 'completed')->count(),
                'completedRecent' => WithdrawalRequest::where('status', 'completed')
                    ->where('processed_at', '>=', $monthStart)
                    ->count(),
                'completedPrevious' => WithdrawalRequest::where('status', 'completed')
                    ->whereBetween('processed_at', [$previousMonthStart, $monthStart])
                    ->count(),
            ];
        });

        return [
            [
                'label' => 'Pending Payouts',
                'value' => number_format($metrics['awaiting'] + $metrics['processing']),
                'sub' => number_format($metrics['awaiting']).' awaiting review · '.number_format($metrics['processing']).' processing',
                // A queue is a snapshot, not a rate — comparing it to a previous
                // period would say nothing useful.
                'delta' => null,
                'deltaLabel' => '',
                'icon' => 'bi-hourglass-split',
                'accent' => 'warning',
            ],
            [
                'label' => 'Completed Payouts',
                'value' => number_format($metrics['completed']),
                'sub' => number_format($metrics['completedRecent']).' in the last 30 days',
                'delta' => $this->percentageDelta($metrics['completedRecent'], $metrics['completedPrevious']),
                'deltaLabel' => 'vs previous 30 days',
                'icon' => 'bi-cash-stack',
                'accent' => 'success',
            ],
        ];
    }

    /**
     * Streak statistics — how many users are keeping one alive, and how long.
     *
     * The subtitle names the rule currently in force (Admin → Settings →
     * Streaks), because "142 active streaks" means something different when a
     * streak is earned by opening the app than by publishing an outfit.
     */
    private function streakCards(): array
    {
        $metrics = Cache::remember('admin_dashboard_streaks', 300, function () {
            $active = Profile::where('current_streak_days', '>', 0);

            $leader = Profile::query()
                ->with('user:id,username')
                ->where('current_streak_days', '>', 0)
                ->orderByDesc('current_streak_days')
                ->first(['user_id', 'current_streak_days']);

            return [
                'active' => $active->clone()->count(),
                'average' => (float) $active->clone()->avg('current_streak_days'),
                'longest' => (int) ($leader?->current_streak_days ?? 0),
                'leader' => $leader?->user?->username ? '@'.$leader->user->username : null,
            ];
        });

        $modeLabel = match (stylebite_streak_mode()) {
            'login' => 'Earned by opening the app daily',
            'any_post' => 'Earned by posting daily',
            default => 'Earned by posting an outfit daily',
        };

        return [
            [
                'label' => 'Active Streaks',
                'value' => number_format($metrics['active']),
                'sub' => $modeLabel,
                'delta' => null,
                'deltaLabel' => '',
                'icon' => 'bi-fire',
                'accent' => 'warning',
            ],
            [
                'label' => 'Longest Streak',
                'value' => number_format($metrics['longest']).' '.Str::plural('day', $metrics['longest']),
                'sub' => $metrics['leader'] ?? 'No active streaks yet',
                'delta' => null,
                'deltaLabel' => '',
                'icon' => 'bi-trophy',
                'accent' => 'success',
            ],
            [
                'label' => 'Average Streak',
                'value' => number_format($metrics['average'], 1).' '.Str::plural('day', (int) round($metrics['average'])),
                'sub' => 'Across users with a live streak',
                'delta' => null,
                'deltaLabel' => '',
                'icon' => 'bi-calendar-check',
                'accent' => 'info',
            ],
        ];
    }

    /**
     * The five best-performing posts and the five best-performing reels.
     *
     * Ranked on the counters kept on `posts`, not by counting rows: those
     * counters are what the app itself shows under each post, and reading them
     * keeps this to one pass over `posts` instead of joining three much larger
     * engagement tables.
     *
     * The two lists are deliberately disjoint — a reel is a video post, so the
     * same item never appears in both charts.
     */
    private function topContent(): array
    {
        return Cache::remember('admin_dashboard_top_content', 300, fn () => [
            'posts' => $this->topContentBy(fn (Builder $query) => $query->where('media_kind', '!=', 'video')),
            'reels' => $this->topContentBy(fn (Builder $query) => $query->where('media_kind', 'video')),
        ]);
    }

    /**
     * Returns plain arrays, not a Collection: this result is cached, and a
     * serialized Collection does not survive the round trip reliably.
     */
    private function topContentBy(callable $scope): array
    {
        $engagement = '(like_count + comment_count + share_count)';

        return Post::query()
            ->with('user:id,username,full_name')
            ->where('status', 'published')
            ->tap($scope)
            // A post nobody touched would render as an empty bar; leave it out
            // rather than padding the chart to five rows.
            ->whereRaw("{$engagement} > 0")
            ->orderByRaw("{$engagement} DESC")
            ->limit(5)
            ->get(['id', 'user_id', 'caption', 'like_count', 'comment_count', 'share_count'])
            ->map(function (Post $post) {
                // Captions carry newlines; a bar label has to stay on one line.
                $caption = trim(preg_replace('/\s+/u', ' ', (string) $post->caption));

                return [
                    'label' => $caption !== '' ? Str::limit($caption, 28) : 'Post #'.$post->id,
                    'author' => $post->user?->username ? '@'.$post->user->username : 'Unknown author',
                    'likes' => (int) $post->like_count,
                    'comments' => (int) $post->comment_count,
                    'shares' => (int) $post->share_count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Engagement totals — likes, comments and shares across feed content.
     *
     * Likes and comments are each summed across every table the action can land
     * in (a like on a comment is still a like), so the cards read as platform
     * totals rather than post-only totals. Memories engagement is deliberately
     * left out: it is a separate module with its own Overview card.
     *
     * Every tally is scoped through to the owning post, so engagement on removed
     * content drops out of the totals — a deleted post takes its likes, comments
     * and shares with it. Without that scope the cards count activity on content
     * nobody can see any more, and stop matching the Overview post counts.
     */
    private function engagementCards(CarbonImmutable $periodStart, CarbonImmutable $previousStart): array
    {
        $metrics = Cache::remember('admin_dashboard_engagement', 300, function () use ($periodStart, $previousStart) {
            $tally = fn (callable $live) => [
                'total' => $live()->count(),
                'current' => $live()->where('created_at', '>=', $periodStart)->count(),
                'previous' => $live()->whereBetween('created_at', [$previousStart, $periodStart->subSecond()])->count(),
            ];

            return [
                'postLikes' => $tally(fn () => PostLike::whereHas('post')),
                'commentLikes' => $tally(fn () => CommentLike::whereHas('comment.post')),
                'replyLikes' => $tally(fn () => ReplyLike::whereHas('reply.comment.post')),
                'comments' => $tally(fn () => Comment::whereHas('post')),
                'replies' => $tally(fn () => CommentReply::whereHas('comment.post')),
                'shares' => $tally(fn () => PostShare::whereHas('post')),
            ];
        });

        $sum = fn (array $keys, string $bucket) => array_sum(array_map(
            fn (string $key) => $metrics[$key][$bucket],
            $keys
        ));

        $likeKeys = ['postLikes', 'commentLikes', 'replyLikes'];
        $commentKeys = ['comments', 'replies'];

        return [
            [
                'label' => 'Likes',
                'value' => number_format($sum($likeKeys, 'total')),
                'sub' => number_format($metrics['postLikes']['total']).' on posts',
                'delta' => $this->percentageDelta($sum($likeKeys, 'current'), $sum($likeKeys, 'previous')),
                'deltaLabel' => 'vs last period',
                'icon' => 'bi-heart-fill',
                'accent' => 'primary',
            ],
            [
                'label' => 'Comments',
                'value' => number_format($sum($commentKeys, 'total')),
                'sub' => number_format($metrics['replies']['total']).' replies',
                'delta' => $this->percentageDelta($sum($commentKeys, 'current'), $sum($commentKeys, 'previous')),
                'deltaLabel' => 'vs last period',
                'icon' => 'bi-chat-dots',
                'accent' => 'info',
            ],
            [
                'label' => 'Shares',
                'value' => number_format($metrics['shares']['total']),
                'sub' => number_format($metrics['shares']['current']).' in the last 14 days',
                'delta' => $this->percentageDelta($metrics['shares']['current'], $metrics['shares']['previous']),
                'deltaLabel' => 'vs last period',
                'icon' => 'bi-share-fill',
                'accent' => 'success',
            ],
        ];
    }

    /**
     * Reel and food-review totals for the Overview cards.
     *
     * A reel is any post carrying video, which is how the reels feed itself
     * selects them — `posts.post_type` has a 'reel' value but nothing ever
     * writes it, the API only accepts 'outfit' and 'food'.
     *
     * Both are subsets of Total Posts, and they overlap each other: a food post
     * shot as a video is counted as a reel and as a food review. The cards are
     * deliberately not meant to add up to the post total.
     *
     * Cached because each count sweeps a large slice of the posts table.
     */
    private function contentCounts(): array
    {
        return Cache::remember('admin_dashboard_content_counts', 300, fn () => [
            'totalReels' => Post::where('media_kind', 'video')->count(),
            'publishedReels' => Post::where('media_kind', 'video')->where('status', 'published')->count(),
            'totalFoodReviews' => Post::where('post_type', 'food')->count(),
            'ratedFoodReviews' => Post::where('post_type', 'food')->where('rating_count', '>', 0)->count(),
        ]);
    }

    /**
     * Audience metrics (DAU / MAU / new signups).
     *
     * Active-user counts come from user_daily_activity, which keeps one row per
     * user per active day; `users.last_seen_at` is overwritten on every request
     * and so cannot answer anything about yesterday. Days are cut in the
     * reporting timezone — activity_date is already stored that way, while
     * signups compare against UTC `created_at`, so those bounds are converted
     * back to UTC. Cached briefly because MAU counts distinct users over 30 days.
     */
    private function audienceCards(CarbonImmutable $now): array
    {
        $localNow = $now->setTimezone(stylebite_reporting_timezone());

        $metrics = Cache::remember('admin_dashboard_audience', 300, function () use ($localNow) {
            $today = $localNow->toDateString();
            $yesterday = $localNow->subDay()->toDateString();
            $monthStart = $localNow->subDays(29)->toDateString();
            $previousMonthStart = $localNow->subDays(59)->toDateString();
            $previousMonthEnd = $localNow->subDays(30)->toDateString();

            $todayStart = $localNow->startOfDay()->setTimezone('UTC');
            $yesterdayStart = $localNow->subDay()->startOfDay()->setTimezone('UTC');
            $weekStart = $localNow->subDays(6)->startOfDay()->setTimezone('UTC');
            $monthStartUtc = $localNow->subDays(29)->startOfDay()->setTimezone('UTC');

            return [
                // One row per user per day, so DAU is a plain count — no DISTINCT needed.
                'dau' => UserDailyActivity::where('activity_date', $today)->count(),
                'dauPrevious' => UserDailyActivity::where('activity_date', $yesterday)->count(),
                'mau' => UserDailyActivity::where('activity_date', '>=', $monthStart)
                    ->distinct()
                    ->count('user_id'),
                'mauPrevious' => UserDailyActivity::whereBetween('activity_date', [$previousMonthStart, $previousMonthEnd])
                    ->distinct()
                    ->count('user_id'),
                'signupsToday' => User::where('created_at', '>=', $todayStart)->count(),
                'signupsYesterday' => User::where('created_at', '>=', $yesterdayStart)
                    ->where('created_at', '<', $todayStart)
                    ->count(),
                'signups7d' => User::where('created_at', '>=', $weekStart)->count(),
                'signups30d' => User::where('created_at', '>=', $monthStartUtc)->count(),
            ];
        });

        return [
            [
                'label' => 'Daily Active Users',
                'value' => number_format($metrics['dau']),
                'sub' => 'Unique users active today',
                'delta' => $this->percentageDelta($metrics['dau'], $metrics['dauPrevious']),
                'deltaLabel' => 'vs yesterday',
                'icon' => 'bi-person-check',
                'accent' => 'success',
            ],
            [
                'label' => 'Monthly Active Users',
                'value' => number_format($metrics['mau']),
                'sub' => 'Unique users active in the last 30 days',
                'delta' => $this->percentageDelta($metrics['mau'], $metrics['mauPrevious']),
                'deltaLabel' => 'vs previous 30 days',
                'icon' => 'bi-people-fill',
                'accent' => 'primary',
            ],
            [
                'label' => 'New Signups',
                'value' => number_format($metrics['signupsToday']),
                'sub' => '7 days: '.number_format($metrics['signups7d']).' · 30 days: '.number_format($metrics['signups30d']),
                'delta' => $this->percentageDelta($metrics['signupsToday'], $metrics['signupsYesterday']),
                'deltaLabel' => 'vs yesterday',
                'icon' => 'bi-person-plus',
                'accent' => 'info',
            ],
        ];
    }

    private function periodDelta(string $model, CarbonImmutable $periodStart, CarbonImmutable $previousStart, ?callable $scope = null, string $column = 'created_at'): int
    {
        $currentQuery = $model::query()->where($column, '>=', $periodStart);
        $previousQuery = $model::query()->whereBetween($column, [$previousStart, $periodStart->subSecond()]);

        if ($scope !== null) {
            $currentQuery = $scope($currentQuery);
            $previousQuery = $scope($previousQuery);
        }

        return $this->percentageDelta($currentQuery->count(), $previousQuery->count());
    }

    private function percentageDelta(int $current, int $previous): int
    {
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

    private function actionCards(array $stats, int $postsUnderReview, int $bannedUsers)
    {
        return collect([
            [
                'label' => 'Posts under review',
                'count' => $postsUnderReview,
                'urgency' => 'med',
                'hint' => 'Hidden until moderator decision',
                'icon' => 'bi-shield-check',
                'route' => route('admin.posts.all_posts'),
            ],
            [
                'label' => 'Pending withdrawals',
                'count' => $stats['pendingWithdrawals'],
                'urgency' => 'med',
                'hint' => 'Approve or reject payouts',
                'icon' => 'bi-wallet2',
                'route' => route('admin.earnings.withdrawals'),
            ],
            [
                'label' => 'Failed pushes',
                'count' => $stats['failedPushes'],
                'urgency' => 'low',
                'hint' => 'Last 24h delivery failures',
                'icon' => 'bi-bell-slash',
                'route' => route('admin.notifications.push_logs'),
            ],
            [
                'label' => 'Banned users',
                'count' => $bannedUsers,
                'urgency' => 'low',
                'hint' => 'Currently restricted from app',
                'icon' => 'bi-person',
                'route' => route('admin.users.all_users', ['status' => 'banned']),
            ],
        ]);
    }
}
