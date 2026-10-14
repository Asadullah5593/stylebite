<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\OptimizePostMedia;
use App\Models\Contest;
use App\Models\ContestInvitation;
use App\Models\ContestParticipant;
use App\Models\ContestSubmission;
use App\Models\ContestVote;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserFollow;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ContestController extends Controller
{
    public function home(Request $request): JsonResponse
    {
        return $this->listContests($request, null);
    }

    public function adminContests(Request $request): JsonResponse
    {
        return $this->listContests($request, 'admin');
    }

    public function profileResults(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $items = Contest::query()
            ->whereIn('id', function ($query) use ($userId): void {
                $query->select('contest_id')
                    ->from('contest_participants')
                    ->where('user_id', $userId)
                    ->where('status', 'approved');
            })
            ->orderByDesc('id')
            ->paginate(20);

        $payload = collect($items->items())->map(function (Contest $contest) use ($userId) {
            $this->finalizeContestIfEnded($contest);

            return $this->contestPayload($contest->fresh(), $userId, true);
        })->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'Joined contest results fetched successfully.',
            'contests' => $payload,
            'pagination' => $this->paginationPayload($items),
        ]);
    }

    public function show(Request $request, int $contestId): JsonResponse
    {
        $contest = Contest::query()->findOrFail($contestId);
        $this->finalizeContestIfEnded($contest);

        return response()->json([
            'status_code' => 1,
            'message' => 'Contest fetched successfully.',
            'contest' => $this->contestPayload($contest->fresh(), (int) $request->user()->id, true),
            'joinable_users' => [],
        ]);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('caption', type: 'string', example: 'My contest outfit.')]
    #[BodyParameter('post_id', type: 'integer', example: 123)]
    #[BodyParameter('asset', type: 'string', format: 'binary')]
    public function joinAdminContest(Request $request, int $contestId): JsonResponse
    {
        return $this->storeContestSubmission($request, $contestId);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('caption', type: 'string', example: 'My contest outfit.')]
    #[BodyParameter('post_id', type: 'integer', example: 123)]
    #[BodyParameter('asset', type: 'string', format: 'binary')]
    public function submitContestOutfit(Request $request, int $contestId): JsonResponse
    {
        return $this->storeContestSubmission($request, $contestId);
    }

    public function vote(Request $request, int $contestId): JsonResponse
    {
        $validated = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:contest_submissions,id'],
            'score' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);

        $contest = Contest::query()->findOrFail($contestId);
        $voter = $request->user();
        $this->finalizeContestIfEnded($contest);

        if (! $this->isVotingWindowOpen($contest->fresh())) {
            return $this->error('Voting is not active right now.');
        }

        $submission = $this->liveSubmissionsQuery($contest->id)
            ->where('id', (int) $validated['submission_id'])
            ->first();

        if (! $submission) {
            return $this->error('This submission is no longer available.');
        }

        if ((int) $submission->user_id === (int) $voter->id) {
            return $this->error('You cannot vote your own submission.');
        }

        DB::transaction(function () use ($contest, $submission, $voter, $validated): void {
            ContestVote::query()->updateOrCreate(
                [
                    'contest_id' => $contest->id,
                    'submission_id' => $submission->id,
                    'voter_user_id' => $voter->id,
                    'vote_type' => 'community',
                ],
                [
                    'score' => (float) $validated['score'],
                    'created_at' => now(),
                ]
            );

            $this->refreshContestScores($contest->id);
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Vote submitted successfully.',
            'contest' => $this->contestPayload($contest->fresh(), (int) $voter->id, true),
        ]);
    }

    public function enrolledPosts(): JsonResponse
    {
        $rows = ContestSubmission::query()
            ->with('contest:id,title,status')
            ->whereHas('contest', function ($query): void {
                $query->whereIn('status', ['active', 'upcoming', 'completed']);
            })
            ->whereHas('post', function ($query): void {
                $query->where('status', 'published')
                    ->where('is_blocked', false)
                    ->where(function ($moderation): void {
                        $moderation->whereNull('moderation_status')
                            ->orWhere('moderation_status', 'clean');
                    });
            })
            ->whereHas('post.media', function ($query): void {
                $query->whereNotNull('file_url')
                    ->where('file_url', '!=', '');
            })
            ->orderByDesc('id')
            ->get(['id', 'post_id', 'contest_id']);

        $posts = $rows
            ->unique(fn (ContestSubmission $row) => (int) $row->post_id)
            ->map(fn (ContestSubmission $row) => [
                'post_id' => (int) $row->post_id,
                'contest_id' => (int) $row->contest_id,
                'contest_title' => (string) ($row->contest?->title ?? ''),
                'contest_status' => (string) ($row->contest?->status ?? ''),
            ])
            ->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'Enrolled contest posts fetched successfully.',
            'post_ids' => $posts->pluck('post_id')->values(),
            'posts' => $posts,
        ]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $items = ContestInvitation::query()
            ->with('contest')
            ->where('receiver_user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status_code' => 1,
            'message' => 'Contest invitations fetched successfully.',
            'invitations' => collect($items->items())->map(fn (ContestInvitation $inv) => [
                'id' => $inv->id,
                'contest_id' => (int) $inv->contest_id,
                'contest_title' => $inv->contest?->title,
                'type' => $inv->request_type,
                'request_type' => $inv->request_type,
                'status' => $inv->status,
                'created_at' => optional($inv->created_at)?->toIso8601String(),
            ])->values(),
            'pagination' => $this->paginationPayload($items),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $validated = $request->validate([
            'scope' => ['nullable', 'in:followers_only,global'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = User::query()->where('id', '!=', $viewer->id)->where('status', 'active');

        if (($validated['scope'] ?? 'followers_only') === 'followers_only') {
            $following = UserFollow::query()
                ->where('follower_user_id', $viewer->id)
                ->where('status', 'accepted')
                ->whereNull('deleted_at')
                ->pluck('following_user_id');
            $followers = UserFollow::query()
                ->where('following_user_id', $viewer->id)
                ->where('status', 'accepted')
                ->whereNull('deleted_at')
                ->pluck('follower_user_id');
            $query->whereIn('id', $following->merge($followers)->unique()->all());
        }

        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function ($inner) use ($q): void {
                $inner->where('username', 'like', '%'.$q.'%')
                    ->orWhere('full_name', 'like', '%'.$q.'%');
            });
        }

        $users = $query->with('profile')->orderBy('username')->limit(50)->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Users fetched successfully.',
            'users' => $users->map(fn (User $user) => [
                'user_id' => (int) $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'city' => $user->profile?->city,
            ])->values(),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $citiesQuery = DB::table('profiles')
            ->select('city')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->groupBy('city')
            ->orderBy('city');

        if (! empty($validated['q'])) {
            $citiesQuery->where('city', 'like', '%'.trim((string) $validated['q']).'%');
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Cities fetched successfully.',
            'cities' => $citiesQuery->limit(100)->pluck('city')->values(),
        ]);
    }

    public function tags(): JsonResponse
    {
        $tags = Tag::query()->orderByDesc('usage_count')->orderBy('name')->limit(100)->get(['id', 'name', 'usage_count']);

        return response()->json([
            'status_code' => 1,
            'message' => 'Tags fetched successfully.',
            'tags' => $tags->map(fn (Tag $tag) => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
                'usage_count' => (int) $tag->usage_count,
            ])->values(),
        ]);
    }

    public function createOneVsOne(): JsonResponse
    {
        return $this->removedTypeError();
    }

    public function createCityVsCity(): JsonResponse
    {
        return $this->removedTypeError();
    }

    public function requestJoinOneVsOne(): JsonResponse
    {
        return $this->removedTypeError();
    }

    public function selectOneVsOneOpponent(): JsonResponse
    {
        return $this->removedTypeError();
    }

    public function joinCityContest(): JsonResponse
    {
        return $this->error('Join this contest with an outfit using the join endpoint.');
    }

    public function respondInvitation(): JsonResponse
    {
        return $this->removedTypeError();
    }

    private function listContests(Request $request, ?string $category): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $query = Contest::query()->whereIn('status', ['active', 'upcoming', 'completed']);

        if ($category === 'admin') {
            $query->where('category', 'admin');
        }

        $contests = $query->latest('id')->paginate(20);

        $payload = collect($contests->items())
            ->map(function (Contest $contest) use ($userId) {
                $this->finalizeContestIfEnded($contest);

                return $this->contestPayload($contest->fresh(), $userId, false);
            })
            ->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'Contests fetched successfully.',
            'contests' => $payload,
            'pagination' => $this->paginationPayload($contests),
        ]);
    }

    private function storeContestSubmission(Request $request, int $contestId): JsonResponse
    {
        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
            'post_id' => ['nullable', 'integer'],
            'asset' => ['required_without:post_id', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,mkv,webm'],
        ], [
            'asset.required_without' => 'Either an asset file or a post_id must be provided.',
        ]);

        $contest = Contest::query()->findOrFail($contestId);
        $user = $request->user();
        $this->finalizeContestIfEnded($contest);
        $contest = $contest->fresh();

        if ($contest->status === 'completed') {
            return $this->error('This contest has ended.');
        }

        if (! $this->isJoinWindowOpen($contest)) {
            return $this->error('Joining is closed for this contest.');
        }

        $alreadySubmitted = $this->liveSubmissionsQuery($contest->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadySubmitted) {
            return $this->error('You have already submitted one post for this contest.');
        }

        $approvedCount = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->where('status', 'approved')
            ->count();

        $alreadyParticipant = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();

        if (
            $contest->max_participants !== null
            && ! $alreadyParticipant
            && $approvedCount >= (int) $contest->max_participants
        ) {
            return $this->error('Contest has reached max participants.');
        }

        if (isset($validated['post_id'])) {
            $existingPost = Post::query()
                ->with(['media' => fn ($query) => $query->orderBy('sort_order')])
                ->whereKey($validated['post_id'])
                ->where('user_id', $user->id)
                ->where('status', 'published')
                ->where('is_blocked', false)
                ->first();

            if (! $existingPost) {
                return $this->error('Selected post was not found or does not belong to you.');
            }

            if ($existingPost->media->isEmpty()) {
                return $this->error('Selected post has no media attached.');
            }

            $post = DB::transaction(function () use ($contest, $user, $existingPost) {
                $this->upsertParticipant($contest->id, $user->id);
                $this->markContestActive($contest);

                ContestSubmission::create([
                    'contest_id' => $contest->id,
                    'user_id' => $user->id,
                    'post_id' => $existingPost->id,
                    'submission_status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $this->refreshCounts($contest);

                return $existingPost;
            });
        } else {
            $file = $request->file('asset');
            $mediaType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
            $uploaded = stylebite_upload_file($file, 'posts/'.$user->id);

            $post = DB::transaction(function () use ($contest, $user, $validated, $uploaded, $mediaType) {
                $this->upsertParticipant($contest->id, $user->id);
                $this->markContestActive($contest);

                $post = Post::create([
                    'user_id' => $user->id,
                    'post_type' => 'outfit',
                    'content_type' => 'fashion',
                    'media_kind' => $mediaType,
                    'feed_type' => 'style',
                    'caption' => $validated['caption'] ?? null,
                    'visibility' => 'public',
                    'status' => 'published',
                    'rating_enabled' => true,
                    'posted_at' => now(),
                    'published_at' => now(),
                ]);

                $upload = MediaUpload::create([
                    'user_id' => $user->id,
                    'source' => 'gallery',
                    'upload_type' => 'contest_asset',
                    'media_type' => $mediaType,
                    'original_file_name' => $uploaded['original_file_name'],
                    'file_path' => $uploaded['file_path'],
                    'file_url' => $uploaded['file_url'],
                    'mime_type' => $uploaded['mime_type'],
                    'size_bytes' => $uploaded['size_bytes'],
                    'storage_type' => 'local',
                    'upload_status' => 'ready',
                    'uploaded_at' => now(),
                ]);

                PostMedia::create([
                    'post_id' => $post->id,
                    'upload_id' => $upload->id,
                    'media_type' => $mediaType,
                    'media_role' => 'original',
                    'file_path' => $uploaded['file_path'],
                    'file_url' => $uploaded['file_url'],
                    'mime_type' => $uploaded['mime_type'],
                    'size_bytes' => $uploaded['size_bytes'],
                    'storage_type' => 'local',
                    'sort_order' => 0,
                    'processing_status' => 'pending',
                ]);

                ContestSubmission::create([
                    'contest_id' => $contest->id,
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'submission_status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $this->refreshCounts($contest);

                return $post;
            });

            foreach ($post->media()->get() as $media) {
                OptimizePostMedia::dispatch($media->id)->afterCommit();
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Contest outfit submitted successfully.',
            'post_id' => $post->id,
            'contest' => $this->contestPayload($contest->fresh(), (int) $user->id, true),
        ], Response::HTTP_CREATED);
    }

    private function liveSubmissionsQuery(int $contestId)
    {
        return ContestSubmission::query()
            ->where('contest_id', $contestId)
            ->whereHas('post', function ($query): void {
                $query->where('status', 'published')
                    ->where('is_blocked', false)
                    ->where(function ($moderation): void {
                        $moderation->whereNull('moderation_status')
                            ->orWhere('moderation_status', 'clean');
                    });
            })
            ->whereHas('post.media', function ($query): void {
                $query->whereNotNull('file_url')
                    ->where('file_url', '!=', '');
            });
    }

    private function contestPlaces(Contest $contest)
    {
        try {
            $contest->loadMissing(['places.winnerUser:id,username']);

            return $contest->places;
        } catch (\Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    private function contestPayload(Contest $contest, ?int $viewerUserId = null, bool $includeDetails = false): array
    {
        $liveQuery = $this->liveSubmissionsQuery($contest->id);
        $submissions = $includeDetails
            ? (clone $liveQuery)
                ->with(['user', 'post.media', 'votes'])
                ->orderByRaw('COALESCE(rank_position, 999999), id ASC')
                ->get()
            : collect();
        $liveMeta = $includeDetails
            ? $submissions
            : $liveQuery->get(['id', 'user_id', 'post_id']);
        $liveUserIds = $liveMeta->pluck('user_id');
        $enrolledPostIds = $liveMeta->pluck('post_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $submissionByUser = $liveMeta->keyBy(fn ($row) => (int) $row->user_id);

        $participants = ContestParticipant::query()
            ->with('user')
            ->where('contest_id', $contest->id)
            ->where('status', 'approved')
            ->when(
                $liveUserIds->isEmpty(),
                fn ($query) => $query->whereRaw('0 = 1'),
                fn ($query) => $query->whereIn('user_id', $liveUserIds->all()),
            )
            ->orderByRaw('COALESCE(rank_position, 999999), id ASC')
            ->get();

        $myParticipant = $viewerUserId === null
            ? null
            : $participants->firstWhere('user_id', $viewerUserId);

        $isParticipant = $myParticipant !== null;
        $alreadySubmitted = $viewerUserId !== null && (
            $includeDetails
                ? $submissions->contains(fn (ContestSubmission $submission) => (int) $submission->user_id === $viewerUserId)
                : $liveUserIds->contains(fn ($userId) => (int) $userId === $viewerUserId)
        );

        $winnerName = null;
        $winnerUserId = null;
        if ($contest->winner_user_id && $participants->contains(
            fn (ContestParticipant $participant) => (int) $participant->user_id === (int) $contest->winner_user_id
        )) {
            $winnerUserId = (int) $contest->winner_user_id;
            $winnerName = $participants->firstWhere('user_id', $winnerUserId)?->user?->username;
        }

        $places = $this->contestPlaces($contest);
        $prizes = $places->map(function ($place) {
            return [
                'place' => (int) $place->place,
                'title' => $place->displayTitle(),
                'amount' => (float) $place->prize_amount,
                'winner_user_id' => $place->winner_user_id ? (int) $place->winner_user_id : null,
                'winner' => $place->winnerUser?->username,
            ];
        })->values();
        $rewardAmount = $prizes->isNotEmpty()
            ? (float) $prizes->sum('amount')
            : (float) ($contest->prize_pool ?? 0);
        $leadPrize = $prizes->first(fn (array $prize) => ! empty($prize['winner']));
        if ($leadPrize) {
            $winnerUserId = $leadPrize['winner_user_id'];
            $winnerName = $leadPrize['winner'];
        }

        return [
            'id' => (int) $contest->id,
            'slug' => $contest->slug,
            'title' => $contest->title,
            'subtitle' => $contest->subtitle,
            'category' => $contest->category,
            'contest_category' => $contest->category,
            'description' => $contest->description,
            'contest_type' => 'contest',
            'status' => $contest->status,
            'visibility' => $contest->visibility,
            'cover_image_url' => stylebite_asset_url($contest->cover_image_url ?: $contest->banner_image_url),
            'banner_image_url' => stylebite_asset_url($contest->banner_image_url ?: $contest->cover_image_url),
            'max_participants' => $contest->max_participants !== null ? (int) $contest->max_participants : null,
            'participant_count' => $participants->count(),
            'submission_count' => $includeDetails ? $submissions->count() : $liveUserIds->count(),
            'total_vote_count' => (int) $contest->total_vote_count,
            'start_at' => optional($contest->start_at)?->toIso8601String(),
            'end_at' => optional($contest->end_at)?->toIso8601String(),
            'enrollment_start_at' => optional($contest->enrollment_start_at)?->toIso8601String(),
            'enrollment_end_at' => optional($contest->enrollment_end_at)?->toIso8601String(),
            'voting_start_at' => optional($contest->voting_start_at)?->toIso8601String(),
            'voting_end_at' => optional($contest->voting_end_at)?->toIso8601String(),
            'winner_user_id' => $winnerUserId,
            'winner' => $winnerName,
            'winner_count' => $prizes->count(),
            'prizes' => $prizes,
            'is_admin_contest' => $contest->category === 'admin',
            'reward_amount' => $rewardAmount,
            'has_reward' => $rewardAmount > 0,
            'featured' => $contest->category === 'admin' && $rewardAmount > 0,
            'can_join' => $this->canJoin($contest, $alreadySubmitted, $isParticipant, $participants->count()),
            'is_participant' => $isParticipant,
            'enrolled_post_ids' => $enrolledPostIds,
            'user_rank' => $myParticipant?->rank_position !== null ? (int) $myParticipant->rank_position : 0,
            'user_score' => $myParticipant?->total_score !== null ? (float) $myParticipant->total_score : 0.0,
            'participants_ranking' => $participants->map(function (ContestParticipant $participant) use ($submissionByUser) {
                $submission = $submissionByUser->get((int) $participant->user_id);

                return [
                    'user_id' => (int) $participant->user_id,
                    'username' => $participant->user?->username,
                    'avatar_url' => stylebite_asset_url($participant->user?->avatar_url),
                    'score' => $participant->total_score !== null ? (float) $participant->total_score : 0.0,
                    'rank' => $participant->rank_position !== null ? (int) $participant->rank_position : 0,
                    'rank_position' => $participant->rank_position !== null ? (int) $participant->rank_position : null,
                    'submission_id' => $submission ? (int) $submission->id : null,
                    'post_id' => $submission ? (int) $submission->post_id : null,
                ];
            })->values(),
            'team_ranking' => [],
            'my_participant' => $myParticipant ? [
                'user_id' => (int) $myParticipant->user_id,
                'username' => $myParticipant->user?->username,
                'avatar_url' => stylebite_asset_url($myParticipant->user?->avatar_url),
                'score' => $myParticipant->total_score !== null ? (float) $myParticipant->total_score : 0.0,
                'rank' => $myParticipant->rank_position !== null ? (int) $myParticipant->rank_position : 0,
                'rank_position' => $myParticipant->rank_position !== null ? (int) $myParticipant->rank_position : null,
            ] : null,
            'submissions' => $includeDetails
                ? $submissions->map(fn (ContestSubmission $submission) => $this->submissionPayload($submission, $viewerUserId))->values()
                : [],
        ];
    }

    private function submissionPayload(ContestSubmission $submission, ?int $viewerUserId): array
    {
        $media = $submission->post?->media?->sortBy('sort_order')->first();
        $viewerVote = $viewerUserId === null
            ? null
            : $submission->votes->first(
                fn (ContestVote $vote) => (int) $vote->voter_user_id === (int) $viewerUserId
                    && $vote->vote_type === 'community'
            );

        return [
            'id' => (int) $submission->id,
            'submission_id' => (int) $submission->id,
            'user_id' => (int) $submission->user_id,
            'username' => $submission->user?->username,
            'full_name' => $submission->user?->full_name,
            'avatar_url' => stylebite_asset_url($submission->user?->avatar_url),
            'post_id' => (int) $submission->post_id,
            'caption' => $submission->post?->caption,
            'post_status' => $submission->post?->status,
            'is_mine' => $viewerUserId !== null && (int) $submission->user_id === (int) $viewerUserId,
            'score' => $submission->final_score !== null ? (float) $submission->final_score : 0.0,
            'total_score' => $submission->final_score !== null ? (float) $submission->final_score : 0.0,
            'total_votes' => (int) $submission->votes->count(),
            // The viewer's own rating (1–5) so the client can show what they
            // gave instead of the aggregate. Null when they haven't voted.
            'viewer_score' => $viewerVote ? (float) $viewerVote->score : null,
            'viewer_has_voted' => $viewerVote !== null,
            'rank' => $submission->rank_position !== null ? (int) $submission->rank_position : 0,
            'rank_position' => $submission->rank_position !== null ? (int) $submission->rank_position : null,
            'submitted_at' => optional($submission->submitted_at)?->toIso8601String(),
            'media' => [
                'type' => $media?->media_type,
                'media_type' => $media?->media_type,
                'url' => stylebite_asset_url($media?->file_url),
                'thumbnail_url' => stylebite_asset_url($media?->thumbnail_url),
            ],
            'post' => $submission->post ? [
                'id' => (int) $submission->post->id,
                'caption' => $submission->post->caption,
                'status' => $submission->post->status,
                'media' => $submission->post->media->sortBy('sort_order')->values()->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'media_type' => $item->media_type,
                    'url' => stylebite_asset_url($item->file_url),
                    'thumbnail_url' => stylebite_asset_url($item->thumbnail_url),
                ])->all(),
            ] : null,
        ];
    }

    private function canJoin(Contest $contest, bool $alreadySubmitted, bool $isParticipant, int $approvedCount): bool
    {
        if ($alreadySubmitted || $contest->status === 'completed') {
            return false;
        }

        if (! $this->isJoinWindowOpen($contest)) {
            return false;
        }

        if (
            $contest->max_participants !== null
            && ! $isParticipant
            && $approvedCount >= (int) $contest->max_participants
        ) {
            return false;
        }

        return true;
    }

    private function isJoinWindowOpen(Contest $contest): bool
    {
        $now = now();

        if ($contest->enrollment_start_at !== null && $now->lt($contest->enrollment_start_at)) {
            return false;
        }

        if ($contest->enrollment_end_at !== null && $now->gt($contest->enrollment_end_at)) {
            return false;
        }

        if ($contest->enrollment_start_at === null && $contest->start_at !== null && $now->lt($contest->start_at)) {
            return false;
        }

        if ($contest->enrollment_end_at === null && $contest->end_at !== null && $now->gt($contest->end_at)) {
            return false;
        }

        return true;
    }

    private function isVotingWindowOpen(Contest $contest): bool
    {
        if ($contest->status === 'completed') {
            return false;
        }

        $now = now();

        if ($contest->voting_start_at !== null && $now->lt($contest->voting_start_at)) {
            return false;
        }

        if ($contest->voting_end_at !== null && $now->gt($contest->voting_end_at)) {
            return false;
        }

        if ($contest->voting_start_at === null && $contest->start_at !== null && $now->lt($contest->start_at)) {
            return false;
        }

        if ($contest->voting_end_at === null && $contest->end_at !== null && $now->gt($contest->end_at)) {
            return false;
        }

        return true;
    }

    private function upsertParticipant(int $contestId, int $userId): void
    {
        ContestParticipant::query()->updateOrCreate(
            [
                'contest_id' => $contestId,
                'user_id' => $userId,
            ],
            [
                'participant_role' => 'team_member',
                'status' => 'approved',
                'approved_at' => now(),
                'joined_at' => now(),
            ]
        );
    }

    private function markContestActive(Contest $contest): void
    {
        if ($contest->status === 'upcoming') {
            $contest->forceFill(['status' => 'active'])->save();
        }
    }

    private function refreshCounts(Contest $contest): void
    {
        $contest->forceFill([
            'participant_count' => ContestParticipant::query()
                ->where('contest_id', $contest->id)
                ->where('status', 'approved')
                ->count(),
            'submission_count' => $this->liveSubmissionsQuery($contest->id)->count(),
        ])->save();
    }

    private function refreshContestScores(int $contestId): void
    {
        $submissions = $this->liveSubmissionsQuery($contestId)->pluck('id');

        foreach ($submissions as $submissionId) {
            $avg = ContestVote::query()->where('submission_id', $submissionId)->avg('score');
            ContestSubmission::query()->where('id', $submissionId)->update([
                'community_score' => $avg,
                'final_score' => $avg,
            ]);
        }

        Contest::query()->where('id', $contestId)->update([
            'total_vote_count' => ContestVote::query()->where('contest_id', $contestId)->count(),
        ]);

        $ranked = $this->liveSubmissionsQuery($contestId)
            ->orderByDesc('final_score')
            ->orderBy('id')
            ->get();

        foreach ($ranked as $index => $submission) {
            ContestSubmission::query()->where('id', $submission->id)->update([
                'rank_position' => $index + 1,
            ]);

            ContestParticipant::query()
                ->where('contest_id', $contestId)
                ->where('user_id', $submission->user_id)
                ->update([
                    'rank_position' => $index + 1,
                    'total_score' => $submission->final_score,
                ]);
        }
    }

    private function finalizeContestIfEnded(Contest $contest): void
    {
        if ($contest->status === 'completed') {
            return;
        }

        $deadline = $contest->voting_end_at ?? $contest->end_at;

        if (! $deadline || now()->lte($deadline)) {
            return;
        }

        $this->refreshContestScores($contest->id);

        $winnerSubmission = $this->liveSubmissionsQuery($contest->id)
            ->orderByDesc('final_score')
            ->orderBy('id')
            ->first();

        $contest->update([
            'status' => 'completed',
            'result_at' => now(),
            'winner_user_id' => $winnerSubmission?->user_id,
            'winner_team_id' => null,
        ]);
    }

    private function paginationPayload($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function removedTypeError(): JsonResponse
    {
        return $this->error('1v1 and city contests were removed. Join an open contest instead.');
    }

    private function error(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
        ], $status);
    }
}
