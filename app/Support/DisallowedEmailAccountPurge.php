<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\Contest;
use App\Models\ContestVote;
use App\Models\Memory;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostRating;
use App\Models\PostShare;
use App\Models\ReplyLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Permanently remove accounts whose email is not Gmail/iCloud, including all
 * content and engagement they created (posts, reels, likes, comments, votes).
 *
 * Staff roles (admin / moderator) are never touched.
 */
final class DisallowedEmailAccountPurge
{
    /**
     * @return Builder<User>
     */
    public static function disallowedUsersQuery(): Builder
    {
        $domains = stylebite_allowed_email_domains();
        $placeholders = implode(',', array_fill(0, count($domains), '?'));

        return User::withTrashed()
            ->whereNotIn('role', ['admin', 'moderator'])
            ->where(function (Builder $query) use ($domains, $placeholders): void {
                $query
                    ->whereNull('email')
                    ->orWhere('email', '')
                    ->orWhereRaw(
                        'LOWER(SUBSTRING_INDEX(email, "@", -1)) NOT IN ('.$placeholders.')',
                        $domains
                    );
            });
    }

    /**
     * @return Collection<int, User>
     */
    public static function preview(int $limit = 500): Collection
    {
        return self::disallowedUsersQuery()
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'email', 'username', 'role', 'status', 'deleted_at']);
    }

    /**
     * @return array{users:int,posts:int,likes:int,votes:int}
     */
    public static function purgeAll(bool $dryRun = true): array
    {
        $stats = ['users' => 0, 'posts' => 0, 'likes' => 0, 'votes' => 0];

        self::disallowedUsersQuery()
            ->orderBy('id')
            ->chunkById(50, function (Collection $users) use (&$stats, $dryRun): void {
                foreach ($users as $user) {
                    $result = self::purgeUser($user, $dryRun);
                    $stats['users'] += $result['users'];
                    $stats['posts'] += $result['posts'];
                    $stats['likes'] += $result['likes'];
                    $stats['votes'] += $result['votes'];
                }
            });

        return $stats;
    }

    /**
     * @return array{users:int,posts:int,likes:int,votes:int,email:?string}
     */
    public static function purgeUser(User $user, bool $dryRun = true): array
    {
        if (in_array((string) $user->role, ['admin', 'moderator'], true)) {
            return ['users' => 0, 'posts' => 0, 'likes' => 0, 'votes' => 0, 'email' => $user->email];
        }

        if (stylebite_email_domain_allowed((string) ($user->email ?? ''))) {
            return ['users' => 0, 'posts' => 0, 'likes' => 0, 'votes' => 0, 'email' => $user->email];
        }

        $userId = (int) $user->id;
        $postCount = Post::withTrashed()->where('user_id', $userId)->count();
        $likeCount = PostLike::query()->where('user_id', $userId)->count();
        $voteCount = ContestVote::query()->where('voter_user_id', $userId)->count();

        if ($dryRun) {
            return [
                'users' => 1,
                'posts' => $postCount,
                'likes' => $likeCount,
                'votes' => $voteCount,
                'email' => $user->email,
            ];
        }

        $likedPostIds = PostLike::query()->where('user_id', $userId)->pluck('post_id');
        $ratedPostIds = PostRating::query()->where('user_id', $userId)->pluck('post_id');
        $commentedPostIds = Comment::query()->where('user_id', $userId)->pluck('post_id');
        $sharedPostIds = PostShare::query()->where('user_id', $userId)->pluck('post_id');
        $contestIds = ContestVote::query()
            ->where('voter_user_id', $userId)
            ->pluck('contest_id')
            ->merge(
                DB::table('contest_submissions')->where('user_id', $userId)->pluck('contest_id')
            )
            ->unique()
            ->filter()
            ->values();

        $mediaPaths = self::collectMediaPaths($userId);

        DB::transaction(function () use ($user, $userId): void {
            // Own content first (hard delete so soft-deleted rows do not linger).
            Post::withTrashed()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->chunkById(100, function (Collection $posts): void {
                    foreach ($posts as $post) {
                        $post->forceDelete();
                    }
                });

            if (class_exists(Memory::class)) {
                Memory::query()
                    ->where('user_id', $userId)
                    ->orderBy('id')
                    ->chunkById(100, function (Collection $memories): void {
                        foreach ($memories as $memory) {
                            if (method_exists($memory, 'forceDelete')) {
                                $memory->forceDelete();
                            } else {
                                $memory->delete();
                            }
                        }
                    });
            }

            ContestVote::query()->where('voter_user_id', $userId)->delete();
            PostRating::query()->where('user_id', $userId)->delete();
            PostLike::query()->where('user_id', $userId)->delete();
            CommentLike::query()->where('user_id', $userId)->delete();
            ReplyLike::query()->where('user_id', $userId)->delete();
            PostShare::query()->where('user_id', $userId)->delete();

            CommentReply::withTrashed()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->chunkById(200, function (Collection $replies): void {
                    foreach ($replies as $reply) {
                        $reply->forceDelete();
                    }
                });

            Comment::withTrashed()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->chunkById(200, function (Collection $comments): void {
                    foreach ($comments as $comment) {
                        $comment->forceDelete();
                    }
                });

            // Sessions / devices / profile cascade with forceDelete when FKs allow.
            $user->forceDelete();
        });

        self::recalculatePostCounters(
            $likedPostIds
                ->merge($ratedPostIds)
                ->merge($commentedPostIds)
                ->merge($sharedPostIds)
                ->unique()
                ->filter()
                ->values()
                ->all()
        );

        foreach ($contestIds as $contestId) {
            $contest = Contest::query()->find($contestId);
            if ($contest) {
                ContestResults::refreshScores($contest);
                $contest->save();
            }
        }

        self::deleteCollectedMediaFiles($mediaPaths);

        Log::info('stylebite.purged_disallowed_email_user', [
            'user_id' => $userId,
            'email' => $user->email,
            'posts' => $postCount,
            'likes' => $likeCount,
            'votes' => $voteCount,
        ]);

        return [
            'users' => 1,
            'posts' => $postCount,
            'likes' => $likeCount,
            'votes' => $voteCount,
            'email' => $user->email,
        ];
    }

    /**
     * @return list<string>
     */
    private static function collectMediaPaths(int $userId): array
    {
        $paths = [];

        $posts = Post::withTrashed()
            ->with(['media' => fn ($q) => $q->withTrashed()])
            ->where('user_id', $userId)
            ->get();

        foreach ($posts as $post) {
            foreach ($post->media as $media) {
                foreach (['file_path', 'path', 'url', 'thumbnail_path'] as $attr) {
                    $value = $media->getAttribute($attr);
                    if (is_string($value) && $value !== '') {
                        $paths[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $paths
     */
    private static function deleteCollectedMediaFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $relative = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
            $candidates = [
                base_path($relative),
                public_path($relative),
                storage_path('app/public/'.$relative),
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && $candidate !== '' && File::isFile($candidate)) {
                    File::delete($candidate);
                }
            }
        }
    }

    /**
     * @param  list<int|string>  $postIds
     */
    private static function recalculatePostCounters(array $postIds): void
    {
        foreach (array_unique(array_map('intval', $postIds)) as $postId) {
            if ($postId <= 0) {
                continue;
            }

            $post = Post::withTrashed()->find($postId);
            if (! $post) {
                continue;
            }

            $average = PostRating::query()->where('post_id', $postId)->avg('rating_value');
            $ratingCount = PostRating::query()->where('post_id', $postId)->count();

            $post->forceFill([
                'like_count' => PostLike::query()->where('post_id', $postId)->count(),
                'comment_count' => Comment::query()->where('post_id', $postId)->where('status', 'active')->count(),
                'share_count' => PostShare::query()->where('post_id', $postId)->count(),
                'rating_avg' => $average !== null ? round((float) $average, 2) : 0,
                'rating_count' => $ratingCount,
            ])->save();
        }
    }
}
