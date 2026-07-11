<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
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
        $post = $this->findAccessiblePost($user->id, $postId);

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

        return response()->json([
            'status_code' => 1,
            'message' => $isLiked ? 'Post liked successfully.' : 'Post unliked successfully.',
            'is_liked' => $isLiked,
            'like_count' => (int) $post->fresh()->like_count,
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
        return $post->rating_enabled;
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
    private function feedListPayload(
        Post $post,
        bool $isLiked,
        bool $isSaved,
        ?int $viewerRating,
        bool $isFollowingAuthor,
    ): array {
        $profile = $post->user?->profile;
        $media = $post->media->sortBy('sort_order')->values();
        $primaryMedia = $media->first();

        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'media_kind' => $post->media_kind,
            'caption' => $post->caption,
            'location' => $post->location_name,
            'dish_name' => $post->dish_name,
            'restaurant' => $post->restaurant,
            'author' => [
                'id' => $post->user?->id,
                'username' => $post->user?->username,
                'display_name' => $profile?->display_name ?? $post->user?->full_name,
                'avatar_url' => stylebite_asset_url($post->user?->avatar_url),
                'is_verified_badge' => (bool) ($profile?->is_verified_badge ?? false),
                'is_following' => $isFollowingAuthor,
            ],
            'media' => $media->map(fn ($item) => $this->mediaPayload($item))->values(),
            'primary_media_type' => $primaryMedia?->media_type,
            'media_count' => $media->count(),
            'has_multiple_media' => $media->count() > 1,
            'engagement' => [
                'like_count' => (int) $post->like_count,
                'comment_count' => (int) $post->comment_count,
                'share_count' => (int) $post->share_count,
                'save_count' => (int) $post->save_count,
                'rating_avg' => $post->rating_avg !== null ? (float) $post->rating_avg : null,
                'rating_count' => (int) $post->rating_count,
            ],
            'viewer_state' => [
                'is_liked' => $isLiked,
                'is_saved' => $isSaved,
                'viewer_rating' => $viewerRating,
                'can_vote' => $post->rating_enabled,
            ],
            'allow_comments' => $post->allow_comments,
            'allow_shares' => $post->allow_shares,
            'rating_enabled' => $post->rating_enabled,
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
            'media' => $media->map(fn ($item) => $this->mediaPayload($item))->values(),
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
                'can_vote' => $post->rating_enabled,
            ],
            'comment_preview' => $commentPreview->values(),
            'allow_comments' => $post->allow_comments,
            'allow_shares' => $post->allow_shares,
            'rating_enabled' => $post->rating_enabled,
            'dish_name' => $post->dish_name,
            'restaurant' => $post->restaurant,
            'posted_at' => optional($post->posted_at)->toDateTimeString(),
            'published_at' => optional($post->published_at)->toDateTimeString(),
            'created_at' => optional($post->created_at)->toDateTimeString(),
        ];
    }

    private function listPosts(Request $request, bool $videoOnly): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:15'],
            'type' => ['nullable', 'string', 'in:outfit,food'],
            'tab' => ['nullable', 'string', 'in:style,bite'],
        ]);

        $user = $request->user();
        // Keep pages small so the client parses/renders quickly and scrolling stays smooth.
        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $requestedType = $validated['type'] ?? $this->resolvePostTypeFromTab($validated['tab'] ?? null) ?? 'outfit';

        // Slim feed list: only the relations a feed card needs (author + media).
        $query = Post::query()
            ->with([
                'user.profile',
                'media' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->where('post_type', $requestedType)
            ->when($videoOnly, fn (Builder $query) => $query->whereHas('media', fn (Builder $media) => $media->where('media_type', 'video')))
            ->when(! $videoOnly, fn (Builder $query) => $query->where('user_id', '!=', $user->id))
            ->tap(fn (Builder $query) => $this->applyFeedVisibilityScope($query, $user->id))
            ->tap(fn (Builder $query) => $this->applyUserBlockScope($query, $user->id))
            ->latest('published_at')
            ->latest('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $postIds = $paginator->getCollection()->pluck('id');
        $authorIds = $paginator->getCollection()->pluck('user_id')->unique()->values();

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
            $payloadKey => $paginator->getCollection()->map(fn (Post $post) => $this->feedListPayload(
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
