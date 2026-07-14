<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memory;
use App\Models\Post;
use App\Models\ProfileBadge;
use App\Models\SavedPost;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return $this->showProfile($request, (string) $request->user()->username);
    }

    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outfits_page' => ['nullable', 'integer', 'min:1'],
            'food_page' => ['nullable', 'integer', 'min:1'],
            'saved_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = User::query()
            ->with(['profile'])
            ->findOrFail($request->user()->id);

        $perPage = 10;
        [$outfitPosts, $outfitPagination] = $this->postsPayload($user->id, $user, 'outfit', (int) ($validated['outfits_page'] ?? 1), $perPage);
        [$foodPosts, $foodPagination] = $this->postsPayload($user->id, $user, 'food', (int) ($validated['food_page'] ?? 1), $perPage);
        [$savedPosts, $savedPagination] = $this->savedPostsPayload($user->id, $user, (int) ($validated['saved_page'] ?? 1), $perPage);

        return response()->json([
            'status_code' => 1,
            'message' => 'Profile overview fetched successfully.',
            'user' => $this->overviewProfilePayload($user),
            'outfit_posts' => $outfitPosts,
            'outfit_pagination' => $outfitPagination,
            'food_posts' => $foodPosts,
            'food_pagination' => $foodPagination,
            'saved_posts' => $savedPosts,
            'saved_pagination' => $savedPagination,
        ]);
    }

    public function publicOverview(Request $request, string $username): JsonResponse
    {
        $validated = $request->validate([
            'outfits_page' => ['nullable', 'integer', 'min:1'],
            'food_page' => ['nullable', 'integer', 'min:1'],
            'saved_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $viewer = $request->user();
        $user = User::query()
            ->with(['profile'])
            ->where('username', $username)
            ->firstOrFail();

        $this->ensureProfileAccessible($viewer->id, $user, 'outfits', true);

        $perPage = 10;
        $isSelf = $viewer->id === (int) $user->id;
        [$outfitPosts, $outfitPagination] = $this->postsPayload($viewer->id, $user, 'outfit', (int) ($validated['outfits_page'] ?? 1), $perPage);
        [$foodPosts, $foodPagination] = $this->postsPayload($viewer->id, $user, 'food', (int) ($validated['food_page'] ?? 1), $perPage);

        if ($isSelf) {
            [$savedPosts, $savedPagination] = $this->savedPostsPayload($viewer->id, $user, (int) ($validated['saved_page'] ?? 1), $perPage);
        } else {
            $savedPosts = collect();
            $savedPagination = $this->emptyPaginationPayload((int) ($validated['saved_page'] ?? 1), $perPage);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Profile overview fetched successfully.',
            'user' => $this->publicOverviewProfilePayload($viewer->id, $user),
            'outfit_posts' => $outfitPosts,
            'outfit_pagination' => $outfitPagination,
            'food_posts' => $foodPosts,
            'food_pagination' => $foodPagination,
            'saved_posts' => $savedPosts,
            'saved_pagination' => $savedPagination,
        ]);
    }

    public function updateOverview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'headline' => ['nullable', 'string', 'max:160'],
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        $profile->forceFill([
            'headline' => $validated['headline'] ?? null,
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Profile overview updated successfully.',
            'user' => $this->overviewProfilePayload($user->fresh('profile')),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('users', 'username')->ignore($request->user()->id)],
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'phone_number' => ['nullable', 'string', 'max:25'],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'in:male,female,non_binary,prefer_not_to_say,other'],
            'birth_date' => ['nullable', 'date'],
            'is_private' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'string', 'in:public,private,followers_only'],
        ], [
            'username.min' => 'Your username must be at least 3 characters long.',
            'username.max' => 'Your username may not be greater than 50 characters.',
            'username.regex' => 'Usernames may only contain lowercase letters, numbers, and underscores.',
            'username.unique' => 'This username is already in use. Please choose another one.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user): void {
            $user->forceFill(array_filter([
                'username' => isset($validated['username']) ? Str::lower($validated['username']) : $user->username,
                'full_name' => $validated['full_name'] ?? $user->full_name,
                'phone_country_code' => $validated['phone_country_code'] ?? $user->phone_country_code,
                'phone_number' => $validated['phone_number'] ?? $user->phone_number,
                'locale' => $validated['locale'] ?? $user->locale,
                'timezone' => $validated['timezone'] ?? $user->timezone,
            ], fn ($value) => $value !== null))->save();

            $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

            $profile->forceFill([
                'display_name' => $validated['display_name'] ?? $profile->display_name,
                'headline' => array_key_exists('headline', $validated) ? $validated['headline'] : $profile->headline,
                'bio' => array_key_exists('bio', $validated) ? $validated['bio'] : $profile->bio,
                'website_url' => array_key_exists('website_url', $validated) ? $validated['website_url'] : $profile->website_url,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $profile->city,
                'country' => array_key_exists('country', $validated) ? $validated['country'] : $profile->country,
                'gender' => array_key_exists('gender', $validated) ? $validated['gender'] : $profile->gender,
                'birth_date' => array_key_exists('birth_date', $validated) ? $validated['birth_date'] : $profile->birth_date,
                'is_private' => $validated['is_private'] ?? $profile->is_private,
                'visibility' => $validated['visibility'] ?? $profile->visibility ?? 'public',
            ])->save();
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Profile updated successfully.',
            'user' => $this->overviewProfilePayload($user->fresh('profile')),
        ]);
    }

    public function usernameAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_]+$/'],
        ]);

        $requestedUsername = Str::lower(trim($validated['username']));
        $currentUser = $request->user();
        $isOwnUsername = $requestedUsername === Str::lower((string) $currentUser->username);
        $isTaken = User::query()
            ->where('username', $requestedUsername)
            ->whereKeyNot($currentUser->id)
            ->exists();

        return response()->json([
            'status_code' => 1,
            'message' => $isOwnUsername
                ? 'This is your current username.'
                : ($isTaken ? 'Username is not available.' : 'Username is available.'),
            'username' => $requestedUsername,
            'is_available' => ! $isTaken,
            'is_current_username' => $isOwnUsername,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at === null && $user->phone_verified_at === null) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Please verify your email or phone before requesting a verification badge.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        DB::transaction(function () use ($profile, $user): void {
            $profile->forceFill([
                'is_verified_badge' => true,
            ])->save();

            ProfileBadge::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'badge_key' => 'verified_user',
                ],
                [
                    'title' => 'Verified User',
                    'icon_key' => 'verified_badge',
                    'status' => 'earned',
                    'sort_order' => 0,
                    'earned_at' => now(),
                ]
            );
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Verification badge granted successfully.',
            'user' => $this->overviewProfilePayload($user->fresh(['profile'])),
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status_code' => 1,
            'message' => 'User image fetched successfully.',
            'image' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
            ],
        ]);
    }

    public function images(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status_code' => 1,
            'message' => 'User images fetched successfully.',
            'images' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'cover_url' => stylebite_asset_url($user->cover_url),
            ],
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $uploadedFile = stylebite_upload_file($validated['image'], 'users/'.$user->id.'/avatar');

        $user->forceFill([
            'avatar_url' => $uploadedFile['file_path'],
        ])->save();

        $user->refresh()->loadMissing('profile');

        return response()->json([
            'status_code' => 1,
            'message' => 'Profile image uploaded successfully.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'cover_url' => stylebite_asset_url($user->cover_url),
            ],
        ]);
    }

    public function cover(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status_code' => 1,
            'message' => 'User cover image fetched successfully.',
            'image' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'cover_url' => stylebite_asset_url($user->cover_url),
            ],
        ]);
    }

    public function uploadCover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $uploadedFile = stylebite_upload_file($validated['image'], 'users/'.$user->id.'/cover');

        $user->forceFill([
            'cover_url' => $uploadedFile['file_path'],
        ])->save();

        $user->refresh()->loadMissing('profile');

        return response()->json([
            'status_code' => 1,
            'message' => 'Cover image uploaded successfully.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'cover_url' => stylebite_asset_url($user->cover_url),
            ],
        ]);
    }

    public function show(Request $request, string $username): JsonResponse
    {
        return $this->showProfile($request, $username);
    }

    public function ratingsDistribution(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $user = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $this->ensureProfileAccessible($viewer->id, $user, 'outfits');

        // Star ratings (1-5) received across the user's published posts.
        $counts = DB::table('post_ratings')
            ->join('posts', 'posts.id', '=', 'post_ratings.post_id')
            ->where('posts.user_id', $user->id)
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->selectRaw('post_ratings.rating_value, COUNT(*) as total')
            ->groupBy('post_ratings.rating_value')
            ->pluck('total', 'rating_value');

        $distribution = [];
        $totalRatings = 0;
        $weightedSum = 0;

        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = (int) ($counts[$star] ?? 0);
            $distribution[(string) $star] = $count;
            $totalRatings += $count;
            $weightedSum += $star * $count;
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Rating distribution fetched successfully',
            'data' => [
                'average_rating' => $totalRatings > 0 ? round($weightedSum / $totalRatings, 1) : 0,
                'total_ratings' => $totalRatings,
                'distribution' => $distribution,
            ],
        ]);
    }

    private function showProfile(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $validated = $request->validate([
            'tab' => ['nullable', 'string', 'in:outfits,saved,food,memories'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tab = $validated['tab'] ?? 'outfits';
        $page = (int) ($validated['page'] ?? 1);
        $perPage = 10;

        $user = User::query()
            ->with(['profile', 'earningsWallet', 'profileBadges' => fn ($query) => $query->orderBy('sort_order')])
            ->where('username', $username)
            ->firstOrFail();

        $this->ensureProfileAccessible($viewer->id, $user, $tab);

        [$items, $pagination] = $this->tabPayload($viewer->id, $user, $tab, $page, $perPage);

        return response()->json([
            'status_code' => 1,
            'message' => 'Profile fetched successfully.',
            'profile' => $this->profilePayload($viewer->id, $user, $tab),
            'active_tab' => $tab,
            'items' => $items,
            'pagination' => $pagination,
        ]);
    }

    private function ensureProfileAccessible(int $viewerUserId, User $targetUser, string $tab, bool $allowBlockedView = false): void
    {
        if (! $allowBlockedView) {
            $viewerBlockedTarget = $targetUser->blockedByEntries()
                ->where('blocker_user_id', $viewerUserId)
                ->exists();

            $targetBlockedViewer = $targetUser->blockedUsersEntries()
                ->where('blocked_user_id', $viewerUserId)
                ->exists();

            if ($viewerBlockedTarget || $targetBlockedViewer) {
                abort(response()->json([
                    'status_code' => 0,
                    'message' => 'Profile not found or not accessible.',
                ], Response::HTTP_NOT_FOUND));
            }
        }

        if ($tab === 'saved' && $viewerUserId !== (int) $targetUser->id) {
            abort(response()->json([
                'status_code' => 0,
                'message' => 'Saved posts are only available for the profile owner.',
            ], Response::HTTP_FORBIDDEN));
        }
    }

    private function tabPayload(int $viewerUserId, User $targetUser, string $tab, int $page, int $perPage): array
    {
        return match ($tab) {
            'saved' => $this->savedPostsPayload($viewerUserId, $targetUser, $page, $perPage),
            'food' => $this->postsPayload($viewerUserId, $targetUser, 'food', $page, $perPage),
            'memories' => $this->memoriesPayload($viewerUserId, $targetUser, $page, $perPage),
            default => $this->postsPayload($viewerUserId, $targetUser, 'outfit', $page, $perPage),
        };
    }

    private function postsPayload(int $viewerUserId, User $targetUser, string $postType, int $page, int $perPage): array
    {
        $query = Post::query()
            ->with(['media' => fn ($query) => $query->orderBy('sort_order')])
            ->where('user_id', $targetUser->id)
            ->where('post_type', $postType)
            ->where('status', 'published')
            ->tap(fn (Builder $query) => $this->applyPostVisibilityScope($query, $viewerUserId, $targetUser->id))
            ->latest('published_at')
            ->latest('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            $paginator->getCollection()->map(fn (Post $post) => [
                'id' => $post->id,
                'post_type' => $post->post_type,
                'caption' => $post->caption,
                'media_kind' => $post->media_kind,
                'location' => $post->location_name,
                'like_count' => (int) $post->like_count,
                'comment_count' => (int) $post->comment_count,
                'share_count' => (int) $post->share_count,
                'save_count' => (int) $post->save_count,
                'rating_avg' => $post->rating_avg !== null ? (float) $post->rating_avg : null,
                'media' => $post->media->map(fn ($media) => [
                    'id' => $media->id,
                    'media_type' => $media->media_type,
                    'file_url' => stylebite_asset_url($media->file_url),
                    'thumbnail_url' => stylebite_asset_url($media->thumbnail_url),
                ])->values(),
                'created_at' => optional($post->created_at)->toDateTimeString(),
            ])->values(),
            $this->paginationPayload($paginator),
        ];
    }

    private function savedPostsPayload(int $viewerUserId, User $targetUser, int $page, int $perPage): array
    {
        $query = SavedPost::query()
            ->with(['post.media' => fn ($query) => $query->orderBy('sort_order')])
            ->where('user_id', $targetUser->id)
            ->latest('created_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            $paginator->getCollection()
                ->filter(fn (SavedPost $savedPost) => $savedPost->post !== null)
                ->map(fn (SavedPost $savedPost) => [
                    'saved_post_id' => $savedPost->id,
                    'saved_at' => optional($savedPost->created_at)->toDateTimeString(),
                    'post' => [
                        'id' => $savedPost->post->id,
                        'post_type' => $savedPost->post->post_type,
                        'caption' => $savedPost->post->caption,
                        'media_kind' => $savedPost->post->media_kind,
                        'location' => $savedPost->post->location_name,
                        'like_count' => (int) $savedPost->post->like_count,
                        'comment_count' => (int) $savedPost->post->comment_count,
                        'share_count' => (int) $savedPost->post->share_count,
                        'save_count' => (int) $savedPost->post->save_count,
                        'media' => $savedPost->post->media->map(fn ($media) => [
                            'id' => $media->id,
                            'media_type' => $media->media_type,
                            'file_url' => stylebite_asset_url($media->file_url),
                            'thumbnail_url' => stylebite_asset_url($media->thumbnail_url),
                        ])->values(),
                    ],
                ])->values(),
            $this->paginationPayload($paginator),
        ];
    }

    private function memoriesPayload(int $viewerUserId, User $targetUser, int $page, int $perPage): array
    {
        $query = Memory::query()
            ->with(['media' => fn ($query) => $query->orderBy('sort_order')])
            ->where('user_id', $targetUser->id)
            ->where('status', 'active')
            ->tap(fn (Builder $query) => $this->applyMemoryVisibilityScope($query, $viewerUserId, $targetUser->id))
            ->latest('memory_date')
            ->latest('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            $paginator->getCollection()->map(fn (Memory $memory) => [
                'id' => $memory->id,
                'title' => $memory->title,
                'short_title' => $memory->short_title,
                'short_description' => $memory->short_description,
                'location' => $memory->location_name,
                'city' => $memory->city,
                'country' => $memory->country,
                'memory_date' => optional($memory->memory_date)->toDateString(),
                'like_count' => (int) $memory->like_count,
                'comment_count' => (int) $memory->comment_count,
                'save_count' => (int) $memory->save_count,
                'rating' => $memory->rating !== null ? (float) $memory->rating : null,
                'media' => $memory->media->map(fn ($media) => [
                    'id' => $media->id,
                    'media_type' => $media->media_type,
                    'file_url' => stylebite_asset_url($media->file_url),
                    'thumbnail_url' => stylebite_asset_url($media->thumbnail_url),
                ])->values(),
            ])->values(),
            $this->paginationPayload($paginator),
        ];
    }

    private function profilePayload(int $viewerUserId, User $user, string $tab): array
    {
        $profile = $user->profile;
        $wallet = $user->earningsWallet;
        $isSelf = $viewerUserId === (int) $user->id;
        $contestEntries = $profile?->contest_entries ?? $user->contestSubmissions()->count();
        $contestWins = $profile?->contest_wins ?? $user->contestSubmissions()->where('rank_position', 1)->count();
        $isFollowing = ! $isSelf && UserFollow::query()
            ->where('follower_user_id', $viewerUserId)
            ->where('following_user_id', $user->id)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();
        $followsYou = ! $isSelf && UserFollow::query()
            ->where('follower_user_id', $user->id)
            ->where('following_user_id', $viewerUserId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $profile?->display_name ?? $user->full_name,
            'headline' => $profile?->headline,
            'full_name' => $user->full_name,
            'avatar_url' => stylebite_asset_url($user->avatar_url),
            'cover_url' => stylebite_asset_url($user->cover_url),
            'bio' => $profile?->bio,
            'website_url' => $profile?->website_url,
            'city' => $profile?->city,
            'country' => $profile?->country,
            'is_verified_badge' => (bool) ($profile?->is_verified_badge ?? false),
            'is_self' => $isSelf,
            'can_edit' => $isSelf,
            'relationship' => [
                'is_following' => $isFollowing,
                'follows_you' => $followsYou,
                'is_mutual_follow' => $isFollowing && $followsYou,
            ],
            'active_tab' => $tab,
            'quick_actions' => [
                'show_earnings' => $isSelf,
                'show_memories' => true,
            ],
            'stats' => [
                'vibe_count' => (int) ($profile?->vibe_count ?? 0),
                'vibe_flow' => (int) ($profile?->following_count ?? 0),
                'follower_count' => (int) ($profile?->follower_count ?? 0),
                'following_count' => (int) ($profile?->following_count ?? 0),
                'post_count' => (int) ($profile?->post_count ?? 0),
                'style_points' => (int) ($profile?->style_points ?? 0),
                'memory_count' => $user->memories()->count(),
                'saved_post_count' => $isSelf ? $user->savedPosts()->count() : null,
            ],
            'earnings' => [
                'currency_code' => $wallet?->currency_code,
                'available_balance' => $wallet?->available_balance !== null ? (float) $wallet->available_balance : null,
                'pending_balance' => $wallet?->pending_balance !== null ? (float) $wallet->pending_balance : null,
                'lifetime_earned' => $wallet?->lifetime_earned !== null ? (float) $wallet->lifetime_earned : null,
                'lifetime_withdrawn' => $wallet?->lifetime_withdrawn !== null ? (float) $wallet->lifetime_withdrawn : null,
            ],
            'streak' => [
                'days' => (int) ($profile?->current_streak_days ?? 0),
                'label' => $profile?->current_streak_label,
            ],
            'badges' => $user->profileBadges->map(fn ($badge) => [
                'id' => $badge->id,
                'badge_key' => $badge->badge_key,
                'title' => $badge->title,
                'icon_key' => $badge->icon_key,
                'status' => $badge->status,
                'earned_at' => optional($badge->earned_at)->toDateTimeString(),
            ])->values(),
            'contest' => [
                'wins' => (int) $contestWins,
                'entries' => (int) $contestEntries,
                'progress_percent' => $contestEntries > 0 ? (float) round(($contestWins / $contestEntries) * 100, 2) : 0.0,
            ],
            'battle' => [
                'wins' => (int) ($profile?->battle_wins ?? 0),
                'label' => $profile?->battle_rank_label,
            ],
        ];
    }

    private function overviewProfilePayload(User $user): array
    {
        $profile = $user->profile;
        $memoryCount = $user->memories()->count();
        $savedPostCount = $user->savedPosts()->count();
        $totalPostLikes = (int) Post::query()
            ->where('user_id', $user->id)
            ->where('status', 'published')
            ->sum('like_count');

        return [
            'profile_id' => $profile?->id,
            'user_id' => $user->id,
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $profile?->display_name ?? $user->full_name,
            'headline' => $profile?->headline,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone_country_code' => $user->phone_country_code,
            'phone_number' => $user->phone_number,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'avatar_url' => stylebite_asset_url($user->avatar_url),
            'cover_url' => stylebite_asset_url($user->cover_url),
            'bio' => $profile?->bio,
            'website_url' => $profile?->website_url,
            'city' => $profile?->city,
            'country' => $profile?->country,
            'gender' => $profile?->gender,
            'birth_date' => optional($profile?->birth_date)?->toDateString(),
            'visibility' => $profile?->visibility ?? 'public',
            'is_private' => (bool) ($profile?->is_private ?? false),
            'is_verified_badge' => (bool) ($profile?->is_verified_badge ?? false),
            'email_verified_at' => optional($user->email_verified_at)?->toDateTimeString(),
            'phone_verified_at' => optional($user->phone_verified_at)?->toDateTimeString(),
            'vibe_count' => (int) ($profile?->vibe_count ?? 0),
            'follower_count' => (int) ($profile?->follower_count ?? 0),
            'following_count' => (int) ($profile?->following_count ?? 0),
            'post_count' => (int) ($profile?->post_count ?? 0),
            'reel_count' => (int) ($profile?->reel_count ?? 0),
            'style_points' => (int) ($profile?->style_points ?? 0),
            'current_streak_days' => (int) ($profile?->current_streak_days ?? 0),
            'current_streak_label' => $profile?->current_streak_label,
            'contest_wins' => (int) ($profile?->contest_wins ?? 0),
            'contest_entries' => (int) ($profile?->contest_entries ?? 0),
            'battle_wins' => (int) ($profile?->battle_wins ?? 0),
            'battle_rank_label' => $profile?->battle_rank_label,
            'memory_count' => $memoryCount,
            'saved_post_count' => $savedPostCount,
            'total_post_likes' => $totalPostLikes,
            'created_at' => optional($profile?->created_at)->toDateTimeString(),
            'updated_at' => optional($profile?->updated_at)->toDateTimeString(),
        ];
    }

    private function publicOverviewProfilePayload(int $viewerUserId, User $user): array
    {
        $payload = $this->overviewProfilePayload($user);
        $isSelf = $viewerUserId === (int) $user->id;
        $isFollowing = ! $isSelf && UserFollow::query()
            ->where('follower_user_id', $viewerUserId)
            ->where('following_user_id', $user->id)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();
        $followsYou = ! $isSelf && UserFollow::query()
            ->where('follower_user_id', $user->id)
            ->where('following_user_id', $viewerUserId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();

        $payload['is_self'] = $isSelf;
        $payload['relationship'] = [
            'is_following' => $isFollowing,
            'follows_you' => $followsYou,
            'is_mutual_follow' => $isFollowing && $followsYou,
        ];

        if (! $isSelf) {
            $payload['saved_post_count'] = null;
        }

        return $payload;
    }

    private function applyPostVisibilityScope(Builder $query, int $viewerUserId, int $ownerUserId): Builder
    {
        return $query->where(function (Builder $visibilityQuery) use ($viewerUserId, $ownerUserId) {
            $visibilityQuery
                ->where('visibility', 'public')
                ->orWhere(fn (Builder $privateQuery) => $privateQuery
                    ->where('visibility', 'private')
                    ->where('user_id', $viewerUserId))
                ->orWhere(function (Builder $followersQuery) use ($viewerUserId, $ownerUserId) {
                    $followersQuery
                        ->where('visibility', 'followers_only')
                        ->where(function (Builder $allowedQuery) use ($viewerUserId, $ownerUserId) {
                            $allowedQuery
                                ->where('user_id', $viewerUserId)
                                ->orWhereExists(function ($followQuery) use ($viewerUserId, $ownerUserId) {
                                    $followQuery
                                        ->selectRaw('1')
                                        ->from('user_follows')
                                        ->where('user_follows.following_user_id', $ownerUserId)
                                        ->where('user_follows.follower_user_id', $viewerUserId)
                                        ->where('user_follows.status', 'accepted')
                                        ->whereNull('user_follows.deleted_at');
                                });
                        });
                });
        });
    }

    private function applyMemoryVisibilityScope(Builder $query, int $viewerUserId, int $ownerUserId): Builder
    {
        return $query->where(function (Builder $visibilityQuery) use ($viewerUserId, $ownerUserId) {
            $visibilityQuery
                ->where('visibility', 'public')
                ->orWhere(fn (Builder $privateQuery) => $privateQuery
                    ->where('visibility', 'private')
                    ->where('user_id', $viewerUserId))
                ->orWhere(function (Builder $followersQuery) use ($viewerUserId, $ownerUserId) {
                    $followersQuery
                        ->where('visibility', 'followers_only')
                        ->where(function (Builder $allowedQuery) use ($viewerUserId, $ownerUserId) {
                            $allowedQuery
                                ->where('user_id', $viewerUserId)
                                ->orWhereExists(function ($followQuery) use ($viewerUserId, $ownerUserId) {
                                    $followQuery
                                        ->selectRaw('1')
                                        ->from('user_follows')
                                        ->where('user_follows.following_user_id', $ownerUserId)
                                        ->where('user_follows.follower_user_id', $viewerUserId)
                                        ->where('user_follows.status', 'accepted')
                                        ->whereNull('user_follows.deleted_at');
                                });
                        });
                });
        });
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function emptyPaginationPayload(int $page, int $perPage): array
    {
        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
            'has_more_pages' => false,
        ];
    }
}
