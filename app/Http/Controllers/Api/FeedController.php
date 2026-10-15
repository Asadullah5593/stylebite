<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ContestPlace;
use App\Models\ContestSubmission;
use App\Support\ContestResults;
use App\Models\CommentLike;
use App\Models\CommentReply;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostRating;
use App\Models\PostShare;
use App\Models\ReplyLike;
use App\Models\SavedPost;
use App\Models\UserFollow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FeedController extends Controller
{
    private const COMMENT_DEPTH_LIMIT = 3;

    public function home(Request $request): JsonResponse
    {
        return $this->listPosts($request, false);
    }

    public function reels(Request $request): JsonResponse
    {
        return $this->listPosts($request, true);
    }

    public function searchPosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'type' => ['nullable', 'string', 'in:outfit,food'],
            'tab' => ['nullable', 'string', 'in:style,bite'],
            'q' => ['nullable', 'string', 'max:191'],
            'query' => ['nullable', 'string', 'max:191'],
        ]);

        $user = $request->user();
        $search = trim((string) ($validated['q'] ?? $validated['query'] ?? ''));
        $requestedType = $validated['type']
            ?? $this->resolvePostTypeFromTab($validated['tab'] ?? null)
            ?? 'outfit';

        $query = $this->discoverPostsQuery((int) ($user?->id ?? 0), $requestedType);

        if ($search === '') {
            $query->whereRaw('0 = 1');
        } else {
            $this->applySearchScope($query, $search);
        }

        return $this->paginatedDiscoverResponse(
            $user,
            $query,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            $requestedType,
            'Search posts fetched successfully.',
            ['search_query' => $search],
        );
    }

    public function hashtagPosts(Request $request, string $tag): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'type' => ['nullable', 'string', 'in:outfit,food'],
        ]);

        $user = $request->user();
        $normalizedTag = $this->normalizeHashtag($tag);
        $requestedType = $validated['type'] ?? null;

        $query = $this->discoverPostsQuery((int) ($user?->id ?? 0), $requestedType);

        if ($normalizedTag === '') {
            $query->whereRaw('0 = 1');
        } else {
            $this->applyHashtagScope($query, $normalizedTag);
        }

        return $this->paginatedDiscoverResponse(
            $user,
            $query,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            $requestedType ?? '',
            'Hashtag posts fetched successfully.',
            ['hashtag' => $normalizedTag],
        );
    }

    public function show(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();
        $post = $this->findAccessiblePost($user->id, $postId, true);
        $commentPage = (int) $request->integer('comments_page', 1);
        $commentsPayload = $this->paginatedCommentsPayload($post, $commentPage, $user->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Feed detail fetched successfully.',
            'post' => $this->feedPayload(
                $post,
                $post->likes->contains('user_id', $user->id),
                $post->saves->contains('user_id', $user->id),
                $post->ratings->firstWhere('user_id', $user->id)?->rating_value,
                $this->isFollowingUser($user->id, $post->user_id),
                $user->id,
            ),
            'can_vote' => $this->canVoteOnPost($post),
            'comments' => $commentsPayload['comments'],
            'comments_pagination' => $commentsPayload['pagination'],
        ]);
    }

    public function comments(Request $request, int $postId): JsonResponse
    {
        $viewerUserId = $request->user()->id;
        $post = $this->findAccessiblePost($viewerUserId, $postId);
        $page = (int) $request->integer('page', 1);
        $commentsPayload = $this->paginatedCommentsPayload($post, $page, $viewerUserId);

        return response()->json([
            'status_code' => 1,
            'message' => 'Comments fetched successfully.',
            'comments' => $commentsPayload['comments'],
            'pagination' => $commentsPayload['pagination'],
        ]);
    }

    public function reelShow(Request $request, int $postId): JsonResponse
    {
        $response = $this->show($request, $postId);
        $payload = $response->getData(true);
        $post = $this->findAccessiblePost($request->user()->id, $postId, true);
        $this->ensureVideoPost($post);
        $payload['message'] = 'Reel detail fetched successfully.';
        $payload['reel'] = $payload['post'];
        unset($payload['post']);

        return response()->json($payload, $response->status());
    }

    public function reelComments(Request $request, int $postId): JsonResponse
    {
        $post = $this->findAccessiblePost($request->user()->id, $postId);
        $this->ensureVideoPost($post);

        return $this->comments($request, $postId);
    }

    public function toggleLike(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();
        $post = $this->findLikeablePost($user->id, $postId);

        $isLiked = DB::transaction(function () use ($post, $user) {
            $existing = PostLike::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $post->decrement('like_count');

                return false;
            }

            PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            $post->increment('like_count');

            return true;
        });

        try {
            ContestResults::refreshPost((int) $post->id);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'status_code' => 1,
            'message' => $isLiked ? 'Post liked successfully.' : 'Post unliked successfully.',
            'is_liked' => $isLiked,
            'like_count' => max(0, (int) $post->like_count),
        ]);
    }

    public function toggleSave(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();
        $post = $this->findAccessiblePost($user->id, $postId);

        $isSaved = DB::transaction(function () use ($post, $user) {
            $existing = SavedPost::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $post->decrement('save_count');

                return false;
            }

            SavedPost::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            $post->increment('save_count');

            return true;
        });

        return response()->json([
            'status_code' => 1,
            'message' => $isSaved ? 'Post saved successfully.' : 'Post unsaved successfully.',
            'is_saved' => $isSaved,
            'save_count' => (int) $post->fresh()->save_count,
        ]);
    }

    public function share(Request $request, int $postId): JsonResponse
    {
        $validated = $request->validate([
            'share_channel' => ['nullable', 'string', 'in:copy_link,direct_message,story,external'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $post = $this->findAccessiblePost($user->id, $postId);

        if (! $post->allow_shares) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Sharing is disabled for this post.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($validated, $post, $user) {
            PostShare::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'share_channel' => $validated['share_channel'] ?? 'copy_link',
                'target_user_id' => $validated['target_user_id'] ?? null,
            ]);

            $post->increment('share_count');
        });

        $post = $this->findAccessiblePost($user->id, $postId, true);

        return response()->json([
            'status_code' => 1,
            'message' => 'Post shared successfully.',
            'share_count' => (int) $post->share_count,
            'latest_share' => $this->sharePayload($post->latestShare),
        ]);
    }

    public function vote(Request $request, int $postId): JsonResponse
    {
        $validated = $request->validate([
            'rating_value' => ['required', 'integer', 'between:1,5'],
        ]);

        $user = $request->user();
        $post = $this->findAccessiblePost($user->id, $postId);
        $canVote = $this->canVoteOnPost($post);

        if (! $canVote) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Voting is not allowed on this post.',
                'can_vote' => false,
            ], Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($validated, $post, $user) {
            PostRating::query()->updateOrCreate(
                [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                ],
                [
                    'rating_value' => $validated['rating_value'],
                ]
            );

            $this->refreshPostRatingStats($post);
        });

        $post = $post->fresh();

        return response()->json([
            'status_code' => 1,
            'message' => 'Vote submitted successfully.',
            'can_vote' => true,
            'viewer_rating' => (int) $validated['rating_value'],
            'rating_avg' => $post->rating_avg !== null ? (float) $post->rating_avg : null,
            'rating_count' => (int) $post->rating_count,
        ]);
    }

    public function comment(Request $request, int $postId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $user = $request->user();
        $post = $this->findAccessiblePost($user->id, $postId);

        if (! $post->allow_comments) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Comments are disabled for this post.',
            ], Response::HTTP_FORBIDDEN);
        }

        $comment = DB::transaction(function () use ($validated, $post, $user) {
            $comment = Comment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);

            $post->increment('comment_count');

            return $comment->load('user.profile');
        });

        $this->sendCommentNotification($post, $comment, $user);

        return response()->json([
            'status_code' => 1,
            'message' => 'Comment added successfully.',
            'comment_count' => (int) $post->fresh()->comment_count,
            'comment' => $this->commentPayload($comment),
        ], Response::HTTP_CREATED);
    }

    private function sendCommentNotification(Post $post, Comment $comment, $actor): void
    {
        $recipientUserId = (int) $post->user_id;

        if ($recipientUserId === (int) $actor->id) {
            return;
        }

        $post->loadMissing(['media' => fn ($query) => $query->orderBy('sort_order')]);

        $postImage = $post->media->first()?->thumbnail_url ?? $post->media->first()?->file_url;
        $actorName = $actor->full_name ?: $actor->username;

        stylebite_notify_user(
            recipientUserId: $recipientUserId,
            actorUserId: (int) $actor->id,
            type: 'comment',
            entityType: 'comment',
            entityId: (int) $comment->id,
            title: 'New comment on your post',
            body: $actorName.' commented: '.Str::limit($comment->body, 120),
            actionUrl: '/posts/'.$post->id,
            image: $postImage,
        );
    }

    public function reply(Request $request, int $commentId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'parent_reply_id' => ['nullable', 'integer', 'exists:comment_replies,id'],
        ]);

        $user = $request->user();
        $comment = Comment::query()->with('post')->findOrFail($commentId);
        $post = $this->findAccessiblePost($user->id, $comment->post_id);

        if (! $post->allow_comments) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Comments are disabled for this post.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (isset($validated['parent_reply_id'])) {
            $parentReply = CommentReply::query()->findOrFail($validated['parent_reply_id']);

            if ((int) $parentReply->comment_id !== (int) $comment->id) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Reply does not belong to the selected comment.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($this->replyDepth($parentReply) >= self::COMMENT_DEPTH_LIMIT) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Only 3 levels of replies are allowed.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $reply = DB::transaction(function () use ($validated, $comment, $user) {
            $reply = CommentReply::create([
                'comment_id' => $comment->id,
                'parent_reply_id' => $validated['parent_reply_id'] ?? null,
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);

            $comment->increment('reply_count');

            if (isset($validated['parent_reply_id'])) {
                CommentReply::query()
                    ->whereKey($validated['parent_reply_id'])
                    ->increment('reply_count');
            }

            return $reply->load('user.profile');
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Reply added successfully.',
            'reply_count' => (int) $comment->fresh()->reply_count,
            'reply' => $this->replyPayload($reply, collect(), collect(), 1),
        ], Response::HTTP_CREATED);
    }

    public function toggleCommentLike(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();
        $comment = Comment::query()->with('post')->findOrFail($commentId);
        $this->findAccessiblePost($user->id, $comment->post_id);

        $isLiked = DB::transaction(function () use ($comment, $user) {
            $existing = CommentLike::query()
                ->where('comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $comment->decrement('like_count');

                return false;
            }

            CommentLike::create([
                'comment_id' => $comment->id,
                'user_id' => $user->id,
            ]);

            $comment->increment('like_count');

            return true;
        });

        return response()->json([
            'status_code' => 1,
            'message' => $isLiked ? 'Comment liked successfully.' : 'Comment unliked successfully.',
            'is_liked' => $isLiked,
            'like_count' => (int) $comment->fresh()->like_count,
        ]);
    }

    public function toggleReplyLike(Request $request, int $replyId): JsonResponse
    {
        $user = $request->user();
        $reply = CommentReply::query()->with('comment.post')->findOrFail($replyId);
        $this->findAccessiblePost($user->id, $reply->comment->post_id);

        $isLiked = DB::transaction(function () use ($reply, $user) {
            $existing = ReplyLike::query()
                ->where('reply_id', $reply->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $reply->decrement('like_count');

                return false;
            }

            ReplyLike::create([
                'reply_id' => $reply->id,
                'user_id' => $user->id,
            ]);

            $reply->increment('like_count');

            return true;
        });

        return response()->json([
            'status_code' => 1,
            'message' => $isLiked ? 'Reply liked successfully.' : 'Reply unliked successfully.',
            'is_liked' => $isLiked,
            'like_count' => (int) $reply->fresh()->like_count,
        ]);
    }

    public function destroyComment(Request $request, int $commentId): JsonResponse
    {
        return $this->deleteCommentRecord($request, $commentId);
    }

    public function destroyPostComment(Request $request, int $postId, int $commentId): JsonResponse
    {
        return $this->deleteCommentRecord($request, $commentId, $postId);
    }

    public function destroyReelComment(Request $request, int $postId, int $commentId): JsonResponse
    {
        $post = $this->findAccessiblePost($request->user()->id, $postId);
        $this->ensureVideoPost($post);

        return $this->deleteCommentRecord($request, $commentId, $postId);
    }

    public function destroyReply(Request $request, int $replyId): JsonResponse
    {
        $user = $request->user();
        $reply = CommentReply::query()->with('comment.post')->findOrFail($replyId);

        if ((int) $reply->user_id !== (int) $user->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You can only delete your own reply.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->findAccessiblePost($user->id, $reply->comment->post_id);

        $deletedCount = DB::transaction(function () use ($reply) {
            $ids = $this->collectReplyTreeIds($reply);
            $activeCount = CommentReply::query()
                ->whereIn('id', $ids)
                ->where('status', 'active')
                ->count();

            CommentReply::query()->whereIn('id', $ids)->update(['status' => 'deleted']);
            CommentReply::query()->whereIn('id', $ids)->get()->each->delete();

            if ($reply->parent_reply_id) {
                $parent = CommentReply::query()->whereKey($reply->parent_reply_id)->first();
                if ($parent) {
                    $parent->forceFill([
                        'reply_count' => max(0, (int) $parent->reply_count - 1),
                    ])->save();
                }
            }

            $comment = Comment::query()->whereKey($reply->comment_id)->first();
            if ($comment) {
                $comment->forceFill([
                    'reply_count' => max(0, (int) $comment->reply_count - $activeCount),
                ])->save();
            }

            return max(1, $activeCount);
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Reply deleted successfully.',
            'deleted_count' => $deletedCount,
        ]);
    }

    private function deleteCommentRecord(Request $request, int $commentId, ?int $postId = null): JsonResponse
    {
        $user = $request->user();
        $comment = Comment::query()->with('post')->findOrFail($commentId);

        if ($postId !== null && (int) $comment->post_id !== (int) $postId) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Comment does not belong to this post.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ((int) $comment->user_id !== (int) $user->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You can only delete your own comment.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->findAccessiblePost($user->id, $comment->post_id);

        $deletedCount = DB::transaction(function () use ($comment) {
            $activeReplies = CommentReply::query()
                ->where('comment_id', $comment->id)
                ->where('status', 'active')
                ->count();

            CommentReply::query()
                ->where('comment_id', $comment->id)
                ->where('status', 'active')
                ->update(['status' => 'deleted']);

            CommentReply::query()
                ->where('comment_id', $comment->id)
                ->whereNull('deleted_at')
                ->get()
                ->each
                ->delete();

            $comment->forceFill([
                'status' => 'deleted',
                'reply_count' => 0,
            ])->save();
            $comment->delete();

            Post::query()
                ->whereKey($comment->post_id)
                ->where('comment_count', '>', 0)
                ->decrement('comment_count');

            return 1 + $activeReplies;
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Comment deleted successfully.',
            'deleted_count' => $deletedCount,
            'comment_count' => (int) Post::query()->whereKey($comment->post_id)->value('comment_count'),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function collectReplyTreeIds(CommentReply $reply): array
    {
        $ids = [(int) $reply->id];
        $frontier = [(int) $reply->id];

        while ($frontier !== []) {
            $children = CommentReply::query()
                ->whereIn('parent_reply_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $children)));
        }

        return $ids;
    }

    /**
     * Remember the viewer's coordinates for the nearby-feed fallback.
     * Throttled: skips the write when the fix is fresh (<15 min) and the user
     * hasn't moved meaningfully (~55m), so scrolling pages doesn't spam writes.
     */
    private function rememberViewerLocation($user, float $lat, float $lng): void
    {
        $profile = $user->profile;

        $isFresh = $profile !== null
            && $profile->last_known_lat !== null
            && $profile->last_known_lng !== null
            && abs((float) $profile->last_known_lat - $lat) < 0.0005
            && abs((float) $profile->last_known_lng - $lng) < 0.0005
            && $profile->last_located_at !== null
            && $profile->last_located_at->gt(now()->subMinutes(15));

        if ($isFresh) {
            return;
        }

        $profile ??= $user->profile()->firstOrCreate(['user_id' => $user->id]);

        $profile->forceFill([
            'last_known_lat' => $lat,
            'last_known_lng' => $lng,
            'last_located_at' => now(),
        ])->save();

        $user->setRelation('profile', $profile);
    }

    /**
     * Geo-fence the feed to a radius (km) around the viewer: cheap bounding-box
     * prefilter (uses the (location_lat, location_lng) index), then an exact
     * haversine cutoff, sorted nearest-first.
     */
    private function applyNearbyScope(Builder $query, float $lat, float $lng, float $radiusKm): void
    {
        $haversine = '(6371 * ACOS(LEAST(1.0,'
            .' COS(RADIANS(?)) * COS(RADIANS(location_lat)) * COS(RADIANS(location_lng) - RADIANS(?))'
            .' + SIN(RADIANS(?)) * SIN(RADIANS(location_lat)))))';

        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / (111.045 * max(cos(deg2rad($lat)), 0.01));

        $query
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->whereBetween('location_lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('location_lng', [$lng - $lngDelta, $lng + $lngDelta])
            ->whereRaw($haversine.' <= ?', [$lat, $lng, $lat, $radiusKm])
            ->select('posts.*')
            ->selectRaw($haversine.' as distance_km', [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->latest('published_at')
            ->latest('id');
    }

    private function resolvePostTypeFromTab(?string $tab): ?string
    {
        return match ($tab) {
            'style' => 'outfit',
            'bite' => 'food',
            default => null,
        };
    }

    private function applyFeedVisibilityScope(Builder $query, int $viewerUserId): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('moderation_status', 'clean')
            ->where('is_blocked', false)
            ->where('is_story', false)
            ->where(function (Builder $visibilityQuery) use ($viewerUserId) {
                $visibilityQuery
                    ->where(function (Builder $publicQuery) {
                        $publicQuery->where('visibility', 'public');
                    })
                    ->orWhere(function (Builder $privateQuery) use ($viewerUserId) {
                        $privateQuery
                            ->where('visibility', 'private')
                            ->where('user_id', $viewerUserId);
                    })
                    ->orWhere(function (Builder $followersQuery) use ($viewerUserId) {
                        $followersQuery
                            ->where('visibility', 'followers_only')
                            ->where(function (Builder $allowedQuery) use ($viewerUserId) {
                                $allowedQuery
                                    ->where('user_id', $viewerUserId)
                                    ->orWhereExists(function ($followQuery) use ($viewerUserId) {
                                        $followQuery
                                            ->selectRaw('1')
                                            ->from('user_follows')
                                            ->whereColumn('user_follows.following_user_id', 'posts.user_id')
                                            ->where('user_follows.follower_user_id', $viewerUserId)
                                            ->where('user_follows.status', 'accepted')
                                            ->whereNull('user_follows.deleted_at');
                                    });
                            });
                    });
            });
    }

    private function applyUserBlockScope(Builder $query, int $viewerUserId): Builder
    {
        return $query
            ->whereDoesntHave('user.blockedUsersEntries', fn (Builder $blockQuery) => $blockQuery->where('blocked_user_id', $viewerUserId))
            ->whereDoesntHave('user.blockedByEntries', fn (Builder $blockQuery) => $blockQuery->where('blocker_user_id', $viewerUserId));
    }

    private function findLikeablePost(int $viewerUserId, int $postId): Post
    {
        $post = Post::query()
            ->whereKey($postId)
            ->tap(fn (Builder $query) => $this->applyFeedVisibilityScope($query, $viewerUserId))
            ->tap(fn (Builder $query) => $this->applyUserBlockScope($query, $viewerUserId))
            ->first();

        if ($post) {
            return $post;
        }

        abort(response()->json([
            'status_code' => 0,
            'message' => 'Post not found or not accessible.',
        ], Response::HTTP_NOT_FOUND));
    }

    private function findAccessiblePost(int $viewerUserId, int $postId, bool $includeDetailRelations = false): Post
    {
        $query = Post::query()
            ->with([
                'user.profile',
                'media' => fn ($query) => $query->orderBy('sort_order'),
                'tags',
                'latestShare.user.profile',
                'latestShare.targetUser.profile',
            ])
            ->withContestState()
            ->whereKey($postId)
            ->tap(fn (Builder $query) => $this->applyFeedVisibilityScope($query, $viewerUserId))
            ->tap(fn (Builder $query) => $this->applyUserBlockScope($query, $viewerUserId));

        if ($includeDetailRelations) {
            $query->with([
                'likes',
                'saves',
                'ratings',
            ]);
        }

        $post = $query->first();

        if ($post) {
            return $post;
        }

        abort(response()->json([
            'status_code' => 0,
            'message' => 'Post not found or not accessible.',
        ], Response::HTTP_NOT_FOUND));
    }

    private function paginatedCommentsPayload(Post $post, int $page, int $viewerUserId): array
    {
        $paginator = Comment::query()
            ->with('user.profile')
            ->where('post_id', $post->id)
            ->where('status', 'active')
            ->latest()
            ->paginate(10, ['*'], 'page', $page);

        return [
            'comments' => $this->buildCommentCollectionPayload($paginator->getCollection(), $viewerUserId),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];
    }

    private function buildCommentCollectionPayload(Collection $comments, int $viewerUserId): Collection
    {
        if ($comments->isEmpty()) {
            return collect();
        }

        $commentIds = $comments->pluck('id');
        $replies = CommentReply::query()
            ->with('user.profile')
            ->whereIn('comment_id', $commentIds)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get();

        $replyGroups = $replies->groupBy('comment_id');
        $commentLikedIds = CommentLike::query()
            ->whereIn('comment_id', $commentIds)
            ->where('user_id', $viewerUserId)
            ->pluck('comment_id');
        $replyLikedIds = $replies->isEmpty()
            ? collect()
            : ReplyLike::query()
                ->whereIn('reply_id', $replies->pluck('id'))
                ->where('user_id', $viewerUserId)
                ->pluck('reply_id');

        return $comments->map(function (Comment $comment) use ($replyGroups, $commentLikedIds, $replyLikedIds) {
            return $this->commentPayload(
                $comment,
                $replyGroups->get($comment->id, collect()),
                $commentLikedIds,
                $replyLikedIds
            );
        })->values();
    }

    private function commentPayload(
        Comment $comment,
        ?Collection $replies = null,
        ?Collection $commentLikedIds = null,
        ?Collection $replyLikedIds = null,
    ): array {
        $replies ??= collect();
        $commentLikedIds ??= collect();
        $replyLikedIds ??= collect();

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'status' => $comment->status,
            'like_count' => (int) $comment->like_count,
            'reply_count' => (int) $comment->reply_count,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
            'viewer_state' => [
                'is_liked' => $commentLikedIds->contains($comment->id),
            ],
            'user' => [
                'id' => $comment->user?->id,
                'username' => $comment->user?->username,
                'display_name' => $comment->user?->profile?->display_name ?? $comment->user?->full_name,
                'avatar_url' => stylebite_asset_url($comment->user?->avatar_url),
            ],
            'replies' => $this->buildReplyTree($replies, null, 1, $replyLikedIds),
        ];
    }

    private function buildReplyTree(
        Collection $replies,
        ?int $parentReplyId = null,
        int $depth = 1,
        ?Collection $replyLikedIds = null,
    ): array
    {
        $replyLikedIds ??= collect();

        return $replies
            ->filter(fn (CommentReply $reply) => (int) ($reply->parent_reply_id ?? 0) === (int) ($parentReplyId ?? 0))
            ->values()
            ->map(fn (CommentReply $reply) => $this->replyPayload($reply, $replies, $replyLikedIds, $depth))
            ->all();
    }

    private function replyPayload(
        CommentReply $reply,
        Collection $replies,
        ?Collection $replyLikedIds = null,
        int $depth = 1,
    ): array
    {
        return [
            'id' => $reply->id,
            'comment_id' => $reply->comment_id,
            'parent_reply_id' => $reply->parent_reply_id,
            'body' => $reply->body,
            'status' => $reply->status,
            'like_count' => (int) $reply->like_count,
            'reply_count' => (int) $reply->reply_count,
            'created_at' => optional($reply->created_at)->toDateTimeString(),
            'viewer_state' => [
                'is_liked' => ($replyLikedIds ?? collect())->contains($reply->id),
            ],
            'user' => [
                'id' => $reply->user?->id,
                'username' => $reply->user?->username,
                'display_name' => $reply->user?->profile?->display_name ?? $reply->user?->full_name,
                'avatar_url' => stylebite_asset_url($reply->user?->avatar_url),
            ],
            'replies' => $depth >= self::COMMENT_DEPTH_LIMIT
                ? []
                : $this->buildReplyTree($replies, $reply->id, $depth + 1, $replyLikedIds),
        ];
    }

    private function sharePayload(?PostShare $share): ?array
    {
        if (! $share) {
            return null;
        }

        return [
            'id' => $share->id,
            'share_channel' => $share->share_channel,
            'shared_at' => optional($share->created_at)->toDateTimeString(),
            'shared_by' => [
                'id' => $share->user?->id,
                'username' => $share->user?->username,
                'display_name' => $share->user?->profile?->display_name ?? $share->user?->full_name,
            ],
            'shared_to' => $share->targetUser
                ? [
                    'id' => $share->targetUser->id,
                    'username' => $share->targetUser->username,
                    'display_name' => $share->targetUser->profile?->display_name ?? $share->targetUser->full_name,
                ]
                : null,
        ];
    }

    private function canVoteOnPost(Post $post): bool
    {
        return $post->isInContest();
    }

    private function refreshPostRatingStats(Post $post): void
    {
        $average = PostRating::query()
            ->where('post_id', $post->id)
            ->avg('rating_value');

        $count = PostRating::query()
            ->where('post_id', $post->id)
            ->count();

        $post->forceFill([
            'rating_avg' => $average,
            'rating_count' => $count,
        ])->save();
    }

    private function isFollowingUser(int $viewerUserId, int $authorUserId): bool
    {
        return UserFollow::query()
            ->where('follower_user_id', $viewerUserId)
            ->where('following_user_id', $authorUserId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Slim serializer for the home feed / reels list. Returns only the fields a
     * feed card needs, and serves the mobile-optimized media rendition (a
     * compressed image or <=720p video) with a poster for smooth scrolling.
     */
    private function latestWinningFeedPost(int $viewerUserId, string $postType): ?Post
    {
        $place = ContestPlace::query()
            ->where('place', 1)
            ->whereNotNull('winner_user_id')
            ->whereHas('contest', fn (Builder $query) => $query->where('status', 'completed'))
            ->with('contest:id,status,result_at')
            ->get()
            ->sortByDesc(fn (ContestPlace $place) => $place->contest?->result_at?->getTimestamp() ?? 0)
            ->first();

        if (! $place) {
            return null;
        }

        $postId = ContestSubmission::query()
            ->where('contest_id', $place->contest_id)
            ->where('user_id', $place->winner_user_id)
            ->whereNotIn('submission_status', ['rejected', 'disqualified'])
            ->whereHas('post', fn (Builder $query) => $query->where('post_type', $postType))
            ->orderByDesc('final_score')
            ->orderByDesc('id')
            ->value('post_id');

        if (! $postId) {
            return null;
        }

        return Post::query()
            ->with([
                'user.profile',
                'media' => fn ($query) => $query->orderBy('sort_order'),
                'tags',
            ])
            ->withContestState()
            ->whereKey($postId)
            ->tap(fn (Builder $query) => $this->applyFeedVisibilityScope($query, $viewerUserId))
            ->tap(fn (Builder $query) => $this->applyUserBlockScope($query, $viewerUserId))
            ->first();
    }

    private function feedListPayload(
        Post $post,
        bool $isLiked,
        bool $isSaved,
        ?int $viewerRating,
        bool $isFollowingAuthor,
        bool $isContestWinner = false,
    ): array {
        $profile = $post->user?->profile;
        $media = $post->media->sortBy('sort_order')->values();
        $primaryMedia = $media->first();

        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'media_kind' => $post->media_kind,
            'visibility' => $post->visibility,
            'caption' => $post->caption,
            'location' => $post->location_name,
            'distance_km' => isset($post->distance_km) ? round((float) $post->distance_km, 2) : null,
            'dish_name' => $post->dish_name,
            'restaurant' => $post->restaurant,
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ])->values()
                : [],
            'author' => [
                'id' => $post->user?->id,
                'username' => $post->user?->username,
                'display_name' => $profile?->display_name ?? $post->user?->full_name,
                'avatar_url' => stylebite_asset_url($post->user?->avatar_url),
                'is_verified_badge' => (bool) ($profile?->is_verified_badge ?? false),
                'is_following' => $isFollowingAuthor,
            ],
            'media' => $media->map(fn ($item) => $this->mediaPayload($item))->values()->all(),
            'images' => $media
                ->filter(fn ($item) => $item->media_type === 'image')
                ->map(fn ($item) => stylebite_asset_url($item->optimized_url ?? $item->file_url))
                ->filter()
                ->values()
                ->all(),
            'primary_media_type' => $primaryMedia?->media_type,
            'media_count' => $media->count(),
            'has_multiple_media' => $media->count() > 1,
            'engagement' => [
                'like_count' => (int) $post->like_count,
                'comment_count' => (int) $post->comment_count,
                'share_count' => (int) $post->share_count,
                'save_count' => (int) $post->save_count,
                'view_count' => (int) $post->view_count,
                'rating_avg' => $post->rating_avg !== null ? (float) $post->rating_avg : null,
                'rating_count' => (int) $post->rating_count,
            ],
            'viewer_state' => [
                'is_liked' => $isLiked,
                'is_saved' => $isSaved,
                'viewer_rating' => $viewerRating,
                'can_vote' => $this->canVoteOnPost($post),
            ],
            'allow_comments' => $post->allow_comments,
            'allow_shares' => $post->allow_shares,
            'rating_enabled' => $this->canVoteOnPost($post),
            'in_contest' => $this->canVoteOnPost($post),
            'contest' => $post->contestBadge(),
            'is_contest_winner' => $isContestWinner,
            'posted_at' => optional($post->posted_at)->toDateTimeString(),
        ];
    }

    /**
     * Media entry that prefers the mobile-optimized rendition and always
     * exposes a poster so clients can render a still while scrolling.
     */
    private function mediaPayload($item): array
    {
        $isOptimized = $item->optimized_url !== null;

        return [
            'id' => $item->id,
            'media_type' => $item->media_type,
            'media_role' => $item->media_role,
            'file_url' => stylebite_asset_url($item->optimized_url ?? $item->file_url),
            'original_url' => stylebite_asset_url($item->file_url),
            'poster_url' => stylebite_asset_url($item->thumbnail_url),
            'thumbnail_url' => stylebite_asset_url($item->thumbnail_url),
            'mime_type' => $item->media_type === 'video' && $isOptimized ? 'video/mp4' : $item->mime_type,
            'width' => $item->optimized_width ?? $item->width,
            'height' => $item->optimized_height ?? $item->height,
            'duration_seconds' => $item->duration_seconds,
            'sort_order' => $item->sort_order,
            'is_optimized' => $isOptimized,
            'processing_status' => $item->processing_status,
        ];
    }

    private function feedPayload(
        Post $post,
        bool $isLiked,
        bool $isSaved,
        ?int $viewerRating,
        bool $isFollowingAuthor,
        int $viewerUserId,
    ): array {
        $profile = $post->user?->profile;
        $media = $post->media->sortBy('sort_order')->values();
        $primaryMedia = $media->first();
        $commentPreview = $post->relationLoaded('comments')
            ? $this->buildCommentCollectionPayload($post->comments, $viewerUserId)
            : collect();

        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'content_type' => $post->content_type,
            'feed_type' => $post->feed_type,
            'media_kind' => $post->media_kind,
            'visibility' => $post->visibility,
            'status' => $post->status,
            'caption' => $post->caption,
            'location' => [
                'name' => $post->location_name,
                'city' => $post->city,
                'country' => $post->country,
                'lat' => $post->location_lat !== null ? (float) $post->location_lat : null,
                'lng' => $post->location_lng !== null ? (float) $post->location_lng : null,
            ],
            'author' => [
                'id' => $post->user?->id,
                'username' => $post->user?->username,
                'full_name' => $post->user?->full_name,
                'display_name' => $profile?->display_name ?? $post->user?->full_name,
                'avatar_url' => stylebite_asset_url($post->user?->avatar_url),
                'bio' => $profile?->bio,
                'city' => $profile?->city,
                'country' => $profile?->country,
                'vibe_count' => $profile?->vibe_count ?? 0,
                'follower_count' => $profile?->follower_count ?? 0,
                'is_private' => (bool) ($profile?->is_private ?? false),
                'is_following' => $isFollowingAuthor,
            ],
            'media' => $media->map(fn ($item) => $this->mediaPayload($item))->values()->all(),
            'images' => $media
                ->filter(fn ($item) => $item->media_type === 'image')
                ->map(fn ($item) => stylebite_asset_url($item->optimized_url ?? $item->file_url))
                ->filter()
                ->values()
                ->all(),
            'comments' => $commentPreview->values(),
            'primary_media_type' => $primaryMedia?->media_type,
            'has_multiple_media' => $media->count() > 1,
            'media_count' => $media->count(),
            'share_count' => (int) $post->share_count,
            'latest_share' => $this->sharePayload($post->latestShare),
            'tags' => $post->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->values(),
            'engagement' => [
                'like_count' => (int) $post->like_count,
                'comment_count' => (int) $post->comment_count,
                'share_count' => (int) $post->share_count,
                'save_count' => (int) $post->save_count,
                'view_count' => (int) $post->view_count,
                'rating_avg' => $post->rating_avg !== null ? (float) $post->rating_avg : null,
                'rating_count' => (int) $post->rating_count,
                'food_rating' => $post->food_rating,
                'service_rating' => $post->service_rating,
                'staff_rating' => $post->staff_rating,
                'ambience_rating' => $post->ambience_rating,
            ],
            'viewer_state' => [
                'is_liked' => $isLiked,
                'is_saved' => $isSaved,
                'viewer_rating' => $viewerRating,
                'can_vote' => $this->canVoteOnPost($post),
            ],
            'comment_preview' => $commentPreview->values(),
            'allow_comments' => $post->allow_comments,
            'allow_shares' => $post->allow_shares,
            'rating_enabled' => $this->canVoteOnPost($post),
            'in_contest' => $this->canVoteOnPost($post),
            'contest' => $post->contestBadge(),
            'dish_name' => $post->dish_name,
            'restaurant' => $post->restaurant,
            'posted_at' => optional($post->posted_at)->toDateTimeString(),
            'published_at' => optional($post->published_at)->toDateTimeString(),
            'created_at' => optional($post->created_at)->toDateTimeString(),
        ];
    }

    private function discoverPostsQuery(int $viewerUserId, ?string $requestedType): Builder
    {
        return Post::query()
            ->with([
                'user.profile',
                'media' => fn ($query) => $query->orderBy('sort_order'),
                'tags',
            ])
            ->withContestState()
            ->when($requestedType !== null && $requestedType !== '', fn (Builder $query) => $query->where('post_type', $requestedType))
            ->tap(fn (Builder $query) => $this->applyFeedVisibilityScope($query, $viewerUserId))
            ->tap(fn (Builder $query) => $this->applyUserBlockScope($query, $viewerUserId));
    }

    private function applySearchScope(Builder $query, string $search): Builder
    {
        $pattern = '%'.Str::lower(addcslashes($search, '%_\\')).'%';

        return $query->where(function (Builder $searchQuery) use ($pattern) {
            $searchQuery
                ->whereRaw("LOWER(COALESCE(caption, '')) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(dish_name, '')) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(restaurant, '')) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(location_name, '')) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(country, '')) LIKE ?", [$pattern])
                ->orWhereHas('tags', function (Builder $tagQuery) use ($pattern) {
                    $tagQuery
                        ->where('normalized_name', 'like', $pattern)
                        ->orWhereRaw('LOWER(name) LIKE ?', [$pattern]);
                })
                ->orWhereHas('user', function (Builder $userQuery) use ($pattern) {
                    $userQuery
                        ->whereRaw('LOWER(username) LIKE ?', [$pattern])
                        ->orWhereRaw("LOWER(COALESCE(full_name, '')) LIKE ?", [$pattern])
                        ->orWhereHas('profile', function (Builder $profileQuery) use ($pattern) {
                            $profileQuery->whereRaw("LOWER(COALESCE(display_name, '')) LIKE ?", [$pattern]);
                        });
                });
        });
    }

    private function applyHashtagScope(Builder $query, string $normalizedTag): Builder
    {
        $captionPattern = '%#'.$normalizedTag.'%';

        return $query->where(function (Builder $hashtagQuery) use ($normalizedTag, $captionPattern) {
            $hashtagQuery
                ->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->where('normalized_name', $normalizedTag))
                ->orWhereRaw("LOWER(COALESCE(caption, '')) LIKE ?", [$captionPattern]);
        });
    }

    private function normalizeHashtag(string $tag): string
    {
        $decoded = trim(urldecode($tag));
        $withoutHash = ltrim($decoded, '#');

        return Str::lower(trim($withoutHash));
    }

    private function paginatedDiscoverResponse(
        $user,
        Builder $query,
        int $page,
        int $perPage,
        string $requestedType,
        string $message,
        array $extra = [],
    ): JsonResponse {
        $query->latest('published_at')->latest('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $postIds = $paginator->getCollection()->pluck('id');
        $authorIds = $paginator->getCollection()->pluck('user_id')->unique()->values();

        $likedPostIds = collect();
        $savedPostIds = collect();
        $postRatings = collect();
        $followingAuthorIds = collect();

        if ($user) {
            $likedPostIds = $user->postLikes()
                ->whereIn('post_id', $postIds)
                ->pluck('post_id');

            $savedPostIds = $user->savedPosts()
                ->whereIn('post_id', $postIds)
                ->pluck('post_id');

            $postRatings = $user->postRatings()
                ->whereIn('post_id', $postIds)
                ->get()
                ->keyBy('post_id');

            $followingAuthorIds = UserFollow::query()
                ->where('follower_user_id', $user->id)
                ->whereIn('following_user_id', $authorIds)
                ->where('status', 'accepted')
                ->pluck('following_user_id');
        }

        return response()->json([
            'status_code' => 1,
            'message' => $message,
            'type' => $requestedType,
            'feed' => $paginator->getCollection()->map(fn (Post $post) => $this->feedListPayload(
                $post,
                $likedPostIds->contains($post->id),
                $savedPostIds->contains($post->id),
                $postRatings->get($post->id)?->rating_value,
                $followingAuthorIds->contains($post->user_id),
            )),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
            ...$extra,
        ]);
    }

    private function listPosts(Request $request, bool $videoOnly): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:15'],
            'type' => ['nullable', 'string', 'in:outfit,food'],
            'tab' => ['nullable', 'string', 'in:style,bite'],
            'discover_type' => ['nullable', 'string', 'in:for_you,nearby'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ], [
            'discover_type.in' => 'The discover type must be one of: for_you, nearby.',
            'lat.required_with' => 'Both lat and lng are required together.',
            'lng.required_with' => 'Both lat and lng are required together.',
        ]);

        $user = $request->user();
        // Keep pages small so the client parses/renders quickly and scrolling stays smooth.
        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $requestedType = $validated['type'] ?? $this->resolvePostTypeFromTab($validated['tab'] ?? null) ?? 'outfit';
        // Coordinates sent with any feed call are remembered on the profile so
        // a later nearby request without GPS can fall back to the last fix;
        // with neither, the regular latest feed is served.
        $hasRequestCoords = isset($validated['lat'], $validated['lng']);

        if ($hasRequestCoords) {
            $this->rememberViewerLocation($user, (float) $validated['lat'], (float) $validated['lng']);
        }

        $viewerCoords = null;
        $locationSource = null;

        if ($hasRequestCoords) {
            $viewerCoords = [(float) $validated['lat'], (float) $validated['lng']];
            $locationSource = 'request';
        } elseif (($validated['discover_type'] ?? null) === 'nearby'
            && $user->profile?->last_known_lat !== null
            && $user->profile?->last_known_lng !== null
        ) {
            $viewerCoords = [(float) $user->profile->last_known_lat, (float) $user->profile->last_known_lng];
            $locationSource = 'last_known';
        }

        $wantsNearby = ($validated['discover_type'] ?? 'for_you') === 'nearby' && $viewerCoords !== null;
        $nearbyRadiusKm = null;

        // Slim feed list: author, media, and tags so feed cards can show hashtags.
        $query = Post::query()
            ->with([
                'user.profile',
                'media' => fn ($query) => $query->orderBy('sort_order'),
                'tags',
            ])
            ->withContestState()
            ->where('post_type', $requestedType)
            ->when($videoOnly, fn (Builder $query) => $query->whereHas('media', fn (Builder $media) => $media->where('media_type', 'video')))
            ->when(! $videoOnly, fn (Builder $query) => $query->where('user_id', '!=', $user->id))
            ->tap(fn (Builder $query) => $this->applyFeedVisibilityScope($query, $user->id))
            ->tap(fn (Builder $query) => $this->applyUserBlockScope($query, $user->id));

        if ($wantsNearby) {
            $nearbyRadiusKm = (float) stylebite_app_config('feed.nearby_radius_km', 10);

            if ($nearbyRadiusKm <= 0) {
                $nearbyRadiusKm = 10.0;
            }

            $this->applyNearbyScope($query, $viewerCoords[0], $viewerCoords[1], $nearbyRadiusKm);
        } else {
            $query->latest('published_at')->latest('id');
        }

        $winnerPost = ! $videoOnly && ! $wantsNearby && $page === 1
            ? $this->latestWinningFeedPost((int) $user->id, $requestedType)
            : null;
        if ($winnerPost) {
            $query->where('id', '!=', $winnerPost->id);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $posts = $paginator->getCollection();
        if ($winnerPost) {
            $posts->prepend($winnerPost);
        }

        $postIds = $posts->pluck('id');
        $authorIds = $posts->pluck('user_id')->unique()->values();

        $likedPostIds = $user->postLikes()
            ->whereIn('post_id', $postIds)
            ->pluck('post_id');

        $savedPostIds = $user->savedPosts()
            ->whereIn('post_id', $postIds)
            ->pluck('post_id');

        $postRatings = $user->postRatings()
            ->whereIn('post_id', $postIds)
            ->get()
            ->keyBy('post_id');

        $followingAuthorIds = UserFollow::query()
            ->where('follower_user_id', $user->id)
            ->whereIn('following_user_id', $authorIds)
            ->where('status', 'accepted')
            ->pluck('following_user_id');

        $payloadKey = $videoOnly ? 'reels' : 'feed';

        return response()->json([
            'status_code' => 1,
            'message' => $videoOnly ? 'Reels fetched successfully.' : 'Home feed fetched successfully.',
            'type' => $requestedType,
            'discover_type' => $wantsNearby ? 'nearby' : 'for_you',
            'nearby_radius_km' => $nearbyRadiusKm,
            'location_source' => $wantsNearby ? $locationSource : null,
            $payloadKey => $posts->map(fn (Post $post) => $this->feedListPayload(
                $post,
                $likedPostIds->contains($post->id),
                $savedPostIds->contains($post->id),
                $postRatings->get($post->id)?->rating_value,
                $followingAuthorIds->contains($post->user_id),
                $winnerPost !== null && (int) $post->id === (int) $winnerPost->id,
            )),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    private function ensureVideoPost(Post $post): void
    {
        $hasVideo = $post->media->contains(fn ($media) => $media->media_type === 'video');

        if (! $hasVideo) {
            abort(response()->json([
                'status_code' => 0,
                'message' => 'Requested reel was not found.',
            ], Response::HTTP_NOT_FOUND));
        }
    }

    private function replyDepth(CommentReply $reply): int
    {
        $depth = 1;
        $cursor = $reply;

        while ($cursor->parent_reply_id !== null) {
            $depth++;
            $cursor = CommentReply::query()->findOrFail($cursor->parent_reply_id);
        }

        return $depth;
    }
}
