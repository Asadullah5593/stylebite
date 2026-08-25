<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Contest;
use App\Models\EarningTransaction;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Support\Facades\Cache;

/**
 * The record counts shown beside each section in the admin sidebar.
 *
 * Each badge equals the count on that section's landing tab, so the number in
 * the sidebar is always the number the admin sees after clicking through —
 * the queries here deliberately mirror each controller's tabCounts().
 *
 * Twelve COUNT(*)s on every admin page load would add up, so the result is
 * shared across all admins and refreshed once a minute; the badges are
 * orientation, not a live feed.
 */
class AdminSidebarCounts
{
    public const CACHE_KEY = 'admin_sidebar_counts';

    public const TTL_SECONDS = 60;

    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, fn () => self::compute());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, int>
     */
    private static function compute(): array
    {
        return [
            'users' => User::withTrashed()->count(),       // Users → All users (includes deleted)
            'social' => UserFollow::withTrashed()->count(), // Social Graph → Follows
            'posts' => Post::count(),                       // Posts → All posts
            'comments' => Comment::count(),                 // Comments → Comments
            'engagement' => PostLike::count(),              // Engagement → Post likes
            'media' => Tag::count(),                        // Media & Tags → Tags
            'memories' => Memory::count(),                  // Memories → Memories
            'messaging' => Message::count(),                // Messaging → Messages
            'notifications' => Notification::count(),       // Notifications → Notifications
            'moderation' => Report::count(),                // Moderation → Reports
            'contests' => Contest::count(),                 // Contests → Contests
            'earnings' => EarningTransaction::count(),      // Earnings → Transactions
        ];
    }
}
