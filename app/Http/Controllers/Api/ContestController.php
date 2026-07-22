<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\OptimizePostMedia;
use App\Models\Contest;
use App\Models\ContestInvitation;
use App\Models\ContestParticipant;
use App\Models\ContestSubmission;
use App\Models\ContestTeam;
use App\Models\ContestTeamMember;
use App\Models\ContestVote;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserFollow;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ContestController extends Controller
{
    /**
     * @deprecated 1 vs 1 contests are no longer supported; always returns 422.
     */
    public function createOneVsOne(Request $request): JsonResponse
    {
        return $this->error('1 vs 1 contests are no longer supported.');
    }

    public function createCityVsCity(Request $request): JsonResponse
    {
        // Participant bounds are admin-configurable (Admin → Settings → Contests).
        // Defaults match the previously hardcoded limits.
        $minParticipants = max(1, (int) stylebite_app_config('contests.min_participants', 2));
        $maxParticipants = max($minParticipants, (int) stylebite_app_config('contests.max_participants', 100000));

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'tags' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'max_participants' => ['nullable', 'integer', 'min:'.$minParticipants, 'max:'.$maxParticipants],
            'city_one' => ['required', 'string', 'max:120', 'different:city_two'],
            'city_two' => ['required', 'string', 'max:120'],
            'enrollment_end_at' => ['required', 'date', 'after:now'],
            'voting_end_at' => ['required', 'date', 'after:enrollment_end_at'],
            'cover_asset' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,mkv,webm'],
        ]);

        $user = $request->user();
        $upload = stylebite_upload_file($request->file('cover_asset'), 'contests/'.$user->id);
        $enrollmentEndAt = Carbon::parse($validated['enrollment_end_at']);
        $votingEndAt = Carbon::parse($validated['voting_end_at']);
        $targetCities = [trim((string) $validated['city_one']), trim((string) $validated['city_two'])];

        $contest = DB::transaction(function () use ($validated, $user, $upload, $enrollmentEndAt, $votingEndAt, $targetCities) {
            $contest = Contest::create([
                'slug' => $this->uniqueContestSlug($validated['title']),
                'creator_user_id' => $user->id,
                'title' => $validated['title'],
                'subtitle' => $validated['tags'] ?? implode(', ', $targetCities),
                'description' => $validated['description'] ?? null,
                'category' => 'community',
                'contest_type' => 'city',
                'status' => 'upcoming',
                'visibility' => 'public',
                'city' => implode(' vs ', $targetCities),
                'max_participants' => isset($validated['max_participants']) ? (int) $validated['max_participants'] : null,
                'cover_image_url' => $upload['file_url'],
                'enrollment_start_at' => now(),
                'enrollment_end_at' => $enrollmentEndAt,
                'voting_start_at' => $enrollmentEndAt,
                'voting_end_at' => $votingEndAt,
                'start_at' => now(),
                'end_at' => $votingEndAt,
                'prize_pool' => 0,
            ]);

            $this->upsertParticipant($contest->id, $user->id, 'creator', 'approved');

            return $contest;
        });

        $this->notifyCityContestUsers($contest, $request->user()->id, $targetCities);

        return response()->json([
            'status_code' => 1,
            'message' => 'City vs City contest created successfully.',
            'contest' => $this->contestPayload($contest->fresh()),
        ], Response::HTTP_CREATED);
    }

    /**
     * @deprecated 1 vs 1 contests are no longer supported; always returns 422.
     */
    public function requestJoinOneVsOne(Request $request, int $contestId): JsonResponse
    {
        return $this->error('1 vs 1 contests are no longer supported.');
    }

    public function respondInvitation(Request $request, int $invitationId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        $user = $request->user();
        $invitation = ContestInvitation::query()->with('contest')->findOrFail($invitationId);

        if ((int) $invitation->receiver_user_id !== (int) $user->id) {
            return $this->error('This invitation does not belong to you.', Response::HTTP_FORBIDDEN);
        }

        if ($invitation->status !== 'pending') {
            return $this->error('Invitation is already processed.');
        }

        $responseMessage = 'Invitation response saved.';

        DB::transaction(function () use ($validated, $invitation, &$responseMessage) {
            $contest = $invitation->contest;
            if (! $contest) {
                return;
            }

            // First-come-first-join for 1v1 invites.
            if (
                $validated['status'] === 'accepted'
                && $contest->contest_type === 'one_vs_one'
                && $invitation->request_type === 'invite'
            ) {
                $hasOpponent = ContestParticipant::query()
                    ->where('contest_id', $contest->id)
                    ->where('status', 'approved')
                    ->where('user_id', '!=', (int) $contest->creator_user_id)
                    ->exists();

                if ($hasOpponent) {
                    $invitation->update([
                        'status' => 'cancelled',
                        'responded_at' => now(),
                    ]);
                    $responseMessage = 'Contest already joined by another user. Better luck next time.';

                    return;
                }
            }

            $invitation->update([
                'status' => $validated['status'],
                'responded_at' => now(),
            ]);

            if (
                $validated['status'] === 'accepted'
                && $contest->contest_type === 'one_vs_one'
                && $invitation->request_type === 'invite'
            ) {
                $this->upsertParticipant($contest->id, (int) $invitation->receiver_user_id, 'team_member', 'approved');
                $contest->update([
                    'participant_count' => 2,
                    'status' => 'active',
                    'voting_start_at' => now(),
                ]);

                ContestInvitation::query()
                    ->where('contest_id', $contest->id)
                    ->where('request_type', 'invite')
                    ->where('status', 'pending')
                    ->where('id', '!=', $invitation->id)
                    ->update([
                        'status' => 'cancelled',
                        'responded_at' => now(),
                    ]);

                $responseMessage = 'You joined successfully. Voting has started now.';
            }
        });

        return response()->json([
            'status_code' => 1,
            'message' => $responseMessage,
        ]);
    }

    /**
     * @deprecated 1 vs 1 contests are no longer supported; always returns 422.
     */
    public function selectOneVsOneOpponent(Request $request, int $contestId): JsonResponse
    {
        return $this->error('1 vs 1 contests are no longer supported.');
    }

    public function joinCityContest(Request $request, int $contestId): JsonResponse
    {
        $contest = Contest::query()->findOrFail($contestId);
        $user = $request->user();

        if ($contest->contest_type !== 'city') {
            return $this->error('Only city vs city contests are supported here.');
        }
        $allowedCities = collect(explode(' vs ', (string) $contest->city))
            ->map(fn ($city) => trim($city))
            ->filter()
            ->map(fn ($city) => Str::lower($city))
            ->values();
        $user->loadMissing('profile');
        $userCity = Str::lower(trim((string) ($user->profile?->city ?? '')));

        if ($allowedCities->isNotEmpty() && ! $allowedCities->contains($userCity)) {
            return $this->error('This contest is not available for your city.');
        }

        if (! $this->isEnrollmentWindowOpen($contest)) {
            return $this->error('Enrollment has ended or not started yet.');
        }

        $count = ContestParticipant::query()->where('contest_id', $contest->id)->count();

        if ($contest->max_participants !== null && $count >= (int) $contest->max_participants) {
            return $this->error('Contest has reached max participants.');
        }

        $this->upsertParticipant($contest->id, $user->id, 'team_member', 'approved');
        $this->assignCityTeam($contest, $user);

        $contest->update([
            'participant_count' => ContestParticipant::query()->where('contest_id', $contest->id)->count(),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'You joined the contest successfully.',
        ]);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('caption', type: 'string', example: 'My contest outfit.')]
    #[BodyParameter('asset', description: 'Outfit media file (jpg, jpeg, png, webp, mp4, mov, avi, mkv, webm).', required: true, type: 'string', format: 'binary')]
    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('caption', description: 'Used only when uploading a new asset; ignored when post_id is provided (the post\'s own caption is used).', type: 'string', example: 'My contest outfit.')]
    #[BodyParameter('post_id', description: 'ID of one of your existing published posts to submit. When provided, asset is not required — the post\'s media and caption are used automatically.', type: 'integer', example: 123)]
    #[BodyParameter('asset', description: 'Outfit media file (jpg, jpeg, png, webp, mp4, mov, avi, mkv, webm). Required unless post_id is provided.', type: 'string', format: 'binary')]
    public function joinAdminContest(Request $request, int $contestId): JsonResponse
    {
        $contest = Contest::query()->findOrFail($contestId);

        if ($contest->category !== 'admin') {
            return $this->error('This endpoint only supports admin contests.');
        }

        if ($contest->contest_type !== 'city') {
            return $this->error('Admin contests must be city contests.');
        }

        return $this->storeContestSubmission($request, $contestId);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('caption', description: 'Used only when uploading a new asset; ignored when post_id is provided (the post\'s own caption is used).', type: 'string', example: 'My contest outfit.')]
    #[BodyParameter('post_id', description: 'ID of one of your existing published posts to submit. When provided, asset is not required — the post\'s media and caption are used automatically.', type: 'integer', example: 123)]
    #[BodyParameter('asset', description: 'Outfit media file (jpg, jpeg, png, webp, mp4, mov, avi, mkv, webm). Required unless post_id is provided.', type: 'string', format: 'binary')]
    public function submitContestOutfit(Request $request, int $contestId): JsonResponse
    {
        return $this->storeContestSubmission($request, $contestId);
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

        $participant = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (! $participant && $this->canAutoJoinContest($contest)) {
            $this->upsertParticipant($contest->id, $user->id, 'team_member', 'approved');
            $participant = ContestParticipant::query()
                ->where('contest_id', $contest->id)
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->first();
        }

        if (! $participant) {
            return $this->error('Only approved participants can submit.');
        }

        if ($contest->contest_type === 'city' && ! $this->isEnrollmentWindowOpen($contest)) {
            return $this->error('Submission is allowed only during enrollment period for city contests.');
        }

        $existingSubmission = ContestSubmission::query()
            ->where('contest_id', $contest->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existingSubmission) {
            return $this->error('You have already submitted one post for this contest.');
        }

        if (isset($validated['post_id'])) {
            // Submit one of the user's existing posts: its media and caption
            // are reused as-is; no new post or upload is created.
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
                ContestSubmission::create([
                    'contest_id' => $contest->id,
                    'user_id' => $user->id,
                    'contest_team_id' => $this->findContestTeamIdForUser($contest->id, $user->id),
                    'post_id' => $existingPost->id,
                    'submission_status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $contest->update([
                    'submission_count' => ContestSubmission::query()->where('contest_id', $contest->id)->count(),
                ]);

                return $existingPost;
            });
        } else {
            $file = $request->file('asset');
            $mediaType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
            $uploaded = stylebite_upload_file($file, 'posts/'.$user->id);

            $post = DB::transaction(function () use ($contest, $user, $validated, $uploaded, $mediaType) {
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
                    'contest_team_id' => $this->findContestTeamIdForUser($contest->id, $user->id),
                    'post_id' => $post->id,
                    'submission_status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $contest->update([
                    'submission_count' => ContestSubmission::query()->where('contest_id', $contest->id)->count(),
                ]);

                return $post;
            });

            foreach ($post->media()->get() as $media) {
                OptimizePostMedia::dispatch($media->id)->afterCommit();
            }
        }

        $contest->update([
            'participant_count' => ContestParticipant::query()
                ->where('contest_id', $contest->id)
                ->where('status', 'approved')
                ->count(),
            'submission_count' => ContestSubmission::query()
                ->where('contest_id', $contest->id)
                ->count(),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Contest outfit submitted successfully.',
            'post_id' => $post->id,
            'contest' => $this->contestPayload($contest->fresh(), (int) $user->id, true),
        ], Response::HTTP_CREATED);
    }

    public function vote(Request $request, int $contestId): JsonResponse
    {
        $validated = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:contest_submissions,id'],
            'score' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);

        $contest = Contest::query()->findOrFail($contestId);
        $voter = $request->user();

        if (! $this->isVotingWindowOpen($contest)) {
            return $this->error('Voting is not active right now.');
        }

        $submission = ContestSubmission::query()
            ->where('id', (int) $validated['submission_id'])
            ->where('contest_id', $contest->id)
            ->firstOrFail();

        if ((int) $submission->user_id === (int) $voter->id) {
            return $this->error('You cannot vote your own submission.');
        }

        DB::transaction(function () use ($contest, $submission, $voter, $validated) {
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
        ]);
    }

    public function home(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $category = $request->query('category');
        $userId = (int) $request->user()->id;

        $query = Contest::query()->whereIn('status', ['active', 'upcoming', 'completed']);

        if (in_array($category, ['admin', 'community'], true)) {
            $query->where('category', $category);
        }

        if ($type === 'city') {
            $query->where('contest_type', 'city');
        }

        $contests = $query->latest('id')->paginate(20);

        $payload = collect($contests->items())
            ->map(function (Contest $contest) use ($userId) {
                $this->finalizeContestIfEnded($contest);

                return $this->contestPayload($contest->fresh(), $userId);
            })
            ->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'Contests fetched successfully.',
            'contests' => $payload,
            'pagination' => [
                'current_page' => $contests->currentPage(),
                'per_page' => $contests->perPage(),
                'total' => $contests->total(),
                'last_page' => $contests->lastPage(),
            ],
        ]);
    }

    public function adminContests(Request $request): JsonResponse
    {
        $request->merge(['category' => 'admin']);

        return $this->home($request);
    }

    public function profileResults(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $items = Contest::query()
            ->whereIn('id', function ($query) use ($userId) {
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
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $contestId): JsonResponse
    {
        $contest = Contest::query()->findOrFail($contestId);
        $user = $request->user();

        $this->finalizeContestIfEnded($contest);

        $isParticipant = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();

        $requiresParticipantAccess = $contest->category !== 'admin' && $contest->contest_type === 'city';

        if ($contest->contest_type === 'city' && $requiresParticipantAccess && ! $isParticipant) {
            return $this->error('City contest result is visible only to participants.', Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Contest fetched successfully.',
            'contest' => $this->contestPayload($contest->fresh(), (int) $user->id, true),
            'joinable_users' => $contest->contest_type === 'city'
                ? $this->cityJoinableUsers($contest)
                : [],
        ]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $items = ContestInvitation::query()
            ->with('contest')
            ->where('receiver_user_id', $userId)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status_code' => 1,
            'message' => 'Contest invitations fetched successfully.',
            'invitations' => collect($items->items())->map(fn (ContestInvitation $inv) => [
                'id' => $inv->id,
                'contest_id' => (int) $inv->contest_id,
                'contest_title' => $inv->contest?->title,
                'request_type' => $inv->request_type,
                'status' => $inv->status,
                'status_message' => $this->invitationStatusMessage($inv->status),
                'expires_at' => optional($inv->expires_at)?->toIso8601String(),
                'created_at' => optional($inv->created_at)?->toIso8601String(),
            ])->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $validated = $request->validate([
            'scope' => ['nullable', 'in:followers_only,global'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $scope = $validated['scope'] ?? 'followers_only';
        $query = User::query()->where('id', '!=', $viewer->id)->where('status', 'active');

        if ($scope === 'followers_only') {
            $allowed = $this->followersLinkedUserIds($viewer->id);
            $query->whereIn('id', $allowed->all());
        }

        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function ($inner) use ($q) {
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

        $cities = $citiesQuery->limit(100)->pluck('city')->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'Cities fetched successfully.',
            'cities' => $cities,
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

    private function createInvitations(Contest $contest, int $senderUserId, array $receiverUserIds, string $requestType): void
    {
        $receiverIds = collect($receiverUserIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($contest->challenge_scope === 'followers_only') {
            $allowedIds = $this->followersLinkedUserIds($senderUserId);
            $receiverIds = $receiverIds->filter(fn (int $id) => $allowedIds->contains($id))->values();
        }

        foreach ($receiverIds as $receiverId) {
            if ($receiverId === $senderUserId) {
                continue;
            }

            ContestInvitation::query()->updateOrCreate(
                [
                    'contest_id' => $contest->id,
                    'sender_user_id' => $senderUserId,
                    'receiver_user_id' => $receiverId,
                    'request_type' => $requestType,
                ],
                [
                    'status' => 'pending',
                    'responded_at' => null,
                    'expires_at' => $contest->start_at,
                ]
            );
        }
    }

    private function isOpponentAllowed(int $viewerUserId, int $opponentUserId, string $scope): bool
    {
        if ($viewerUserId === $opponentUserId) {
            return false;
        }

        if ($scope === 'global') {
            return true;
        }

        $allowed = $this->followersLinkedUserIds($viewerUserId);

        return $allowed->contains($opponentUserId);
    }

    private function followersLinkedUserIds(int $userId)
    {
        $following = UserFollow::query()
            ->where('follower_user_id', $userId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->pluck('following_user_id');

        $followers = UserFollow::query()
            ->where('following_user_id', $userId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->pluck('follower_user_id');

        return $following->merge($followers)->unique()->values();
    }

    private function upsertParticipant(int $contestId, int $userId, string $role, string $status): void
    {
        ContestParticipant::query()->updateOrCreate(
            [
                'contest_id' => $contestId,
                'user_id' => $userId,
            ],
            [
                'participant_role' => $role,
                'status' => $status,
                'approved_at' => $status === 'approved' ? now() : null,
                'joined_at' => now(),
            ]
        );
    }

    private function canAutoJoinContest(Contest $contest): bool
    {
        return $contest->category === 'admin' || in_array($contest->contest_type, ['group', 'brand'], true);
    }

    private function assignCityTeam(Contest $contest, User $user): void
    {
        $user->loadMissing('profile');
        $city = trim((string) ($user->profile?->city ?? 'Unknown')) ?: 'Unknown';
        $team = ContestTeam::query()->firstOrCreate(
            [
                'contest_id' => $contest->id,
                'name' => $city,
            ],
            [
                'city' => $city,
            ]
        );

        ContestTeamMember::query()->updateOrCreate(
            [
                'contest_team_id' => $team->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'member',
                'joined_at' => now(),
            ]
        );
    }

    private function findContestTeamIdForUser(int $contestId, int $userId): ?int
    {
        return ContestTeamMember::query()
            ->join('contest_teams', 'contest_teams.id', '=', 'contest_team_members.contest_team_id')
            ->where('contest_teams.contest_id', $contestId)
            ->where('contest_team_members.user_id', $userId)
            ->value('contest_team_members.contest_team_id');
    }

    private function isEnrollmentWindowOpen(Contest $contest): bool
    {
        $now = now();

        return ($contest->enrollment_start_at === null || $now->gte($contest->enrollment_start_at))
            && ($contest->enrollment_end_at === null || $now->lte($contest->enrollment_end_at));
    }

    private function isVotingWindowOpen(Contest $contest): bool
    {
        $now = now();

        return ($contest->voting_start_at === null || $now->gte($contest->voting_start_at))
            && ($contest->voting_end_at === null || $now->lte($contest->voting_end_at));
    }

    private function submissionPayload(ContestSubmission $submission): array
    {
        $media = $submission->post?->media?->sortBy('sort_order')->first();
        $team = $submission->team;

        return [
            'submission_id' => (int) $submission->id,
            'user_id' => (int) $submission->user_id,
            'username' => $submission->user?->username,
            'full_name' => $submission->user?->full_name,
            'post_id' => (int) $submission->post_id,
            'caption' => $submission->post?->caption,
            'post_status' => $submission->post?->status,
            'score' => $submission->final_score !== null ? (float) $submission->final_score : 0.0,
            'rank_position' => $submission->rank_position !== null ? (int) $submission->rank_position : null,
            'seed' => $submission->rank_position !== null ? (int) $submission->rank_position : null,
            'submitted_at' => optional($submission->submitted_at)?->toIso8601String(),
            'team' => $team ? [
                'team_id' => (int) $team->id,
                'name' => $team->name,
                'city' => $team->city,
                'score' => $team->score !== null ? (float) $team->score : 0.0,
                'rank_position' => $team->rank_position !== null ? (int) $team->rank_position : null,
                'seed' => $team->rank_position !== null ? (int) $team->rank_position : null,
            ] : null,
            'media' => [
                'type' => $media?->media_type,
                'url' => stylebite_asset_url($media?->file_url),
                'thumbnail_url' => stylebite_asset_url($media?->thumbnail_url),
            ],
            'post' => $submission->post ? [
                'id' => (int) $submission->post->id,
                'caption' => $submission->post->caption,
                'status' => $submission->post->status,
                'rating_avg' => $submission->post->rating_avg !== null ? (float) $submission->post->rating_avg : null,
                'rating_count' => (int) $submission->post->rating_count,
                'total_rating' => $submission->post->rating_avg !== null ? (float) $submission->post->rating_avg : null,
                'media' => $submission->post->media->sortBy('sort_order')->values()->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'media_type' => $item->media_type,
                    'media_role' => $item->media_role,
                    'url' => stylebite_asset_url($item->file_url),
                    'thumbnail_url' => stylebite_asset_url($item->thumbnail_url),
                    'sort_order' => (int) $item->sort_order,
                ])->all(),
            ] : null,
        ];
    }

    private function refreshContestScores(int $contestId): void
    {
        $submissions = ContestSubmission::query()
            ->where('contest_id', $contestId)
            ->pluck('id');

        foreach ($submissions as $submissionId) {
            $avg = ContestVote::query()->where('submission_id', $submissionId)->avg('score');
            ContestSubmission::query()->where('id', $submissionId)->update([
                'community_score' => $avg,
                'final_score' => $avg,
            ]);
        }

        $contestVoteCount = ContestVote::query()->where('contest_id', $contestId)->count();
        Contest::query()->where('id', $contestId)->update(['total_vote_count' => $contestVoteCount]);

        $ranked = ContestSubmission::query()
            ->where('contest_id', $contestId)
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

        $cityContest = Contest::query()->find($contestId);

        if ($cityContest && $cityContest->contest_type === 'city') {
            $teams = ContestTeam::query()->where('contest_id', $contestId)->get();
            foreach ($teams as $team) {
                $score = ContestSubmission::query()->where('contest_team_id', $team->id)->sum('final_score');
                $team->update(['score' => $score]);
            }

            $rankedTeams = ContestTeam::query()
                ->where('contest_id', $contestId)
                ->orderByDesc('score')
                ->orderBy('id')
                ->get();

            foreach ($rankedTeams as $idx => $team) {
                $team->update(['rank_position' => $idx + 1]);
            }
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

        $winnerSubmission = ContestSubmission::query()
            ->where('contest_id', $contest->id)
            ->orderBy('rank_position')
            ->first();

        $winnerTeam = ContestTeam::query()
            ->where('contest_id', $contest->id)
            ->orderBy('rank_position')
            ->first();

        $contest->update([
            'status' => 'completed',
            'result_at' => now(),
            'winner_user_id' => $winnerSubmission?->user_id,
            'winner_team_id' => $winnerTeam?->id,
        ]);
    }

    private function contestPayload(Contest $contest, ?int $viewerUserId = null, bool $includeDetails = false): array
    {
        $participants = ContestParticipant::query()
            ->with('user')
            ->where('contest_id', $contest->id)
            ->where('status', 'approved')
            ->orderByRaw('COALESCE(rank_position, 999999), id ASC')
            ->get();

        $submissions = ContestSubmission::query()
            ->with(['user', 'post.media'])
            ->where('contest_id', $contest->id)
            ->orderByRaw('COALESCE(rank_position, 999999), id ASC')
            ->get();

        $teamRankings = ContestTeam::query()
            ->where('contest_id', $contest->id)
            ->orderByRaw('COALESCE(rank_position, 999999), id ASC')
            ->get()
            ->map(fn (ContestTeam $team) => [
                'team_id' => (int) $team->id,
                'name' => $team->name,
                'city' => $team->city,
                'score' => $team->score !== null ? (float) $team->score : 0.0,
                'rank_position' => $team->rank_position !== null ? (int) $team->rank_position : null,
                'seed' => $team->rank_position !== null ? (int) $team->rank_position : null,
            ])
            ->values();

        $myParticipant = null;
        $myTeam = null;

        if ($viewerUserId !== null) {
            $participantRecord = $participants->firstWhere('user_id', $viewerUserId);
            if ($participantRecord) {
                $myParticipant = [
                    'user_id' => (int) $participantRecord->user_id,
                    'username' => $participantRecord->user?->username,
                    'avatar_url' => stylebite_asset_url($participantRecord->user?->avatar_url),
                    'score' => $participantRecord->total_score !== null ? (float) $participantRecord->total_score : 0.0,
                    'rank_position' => $participantRecord->rank_position !== null ? (int) $participantRecord->rank_position : null,
                    'seed' => $participantRecord->rank_position !== null ? (int) $participantRecord->rank_position : null,
                ];
            }

            $teamMember = ContestTeamMember::query()
                ->with('team')
                ->where('user_id', $viewerUserId)
                ->whereHas('team', fn ($query) => $query->where('contest_id', $contest->id))
                ->first();

            if ($teamMember?->team) {
                $myTeam = [
                    'team_id' => (int) $teamMember->team->id,
                    'name' => $teamMember->team->name,
                    'city' => $teamMember->team->city,
                    'score' => $teamMember->team->score !== null ? (float) $teamMember->team->score : 0.0,
                    'rank_position' => $teamMember->team->rank_position !== null ? (int) $teamMember->team->rank_position : null,
                    'seed' => $teamMember->team->rank_position !== null ? (int) $teamMember->team->rank_position : null,
                ];
            }
        }

        return [
            'id' => (int) $contest->id,
            'slug' => $contest->slug,
            'title' => $contest->title,
            'subtitle' => $contest->subtitle,
            'category' => $contest->subtitle,
            'contest_category' => $contest->category,
            'description' => $contest->description,
            'contest_type' => $contest->contest_type,
            'status' => $contest->status,
            'challenge_scope' => $contest->challenge_scope,
            'visibility' => $contest->visibility,
            'cover_image_url' => stylebite_asset_url($contest->cover_image_url),
            'max_participants' => $contest->max_participants !== null ? (int) $contest->max_participants : null,
            'participant_count' => (int) $contest->participant_count,
            'submission_count' => (int) $contest->submission_count,
            'total_vote_count' => (int) $contest->total_vote_count,
            'start_at' => optional($contest->start_at)?->toIso8601String(),
            'end_at' => optional($contest->end_at)?->toIso8601String(),
            'enrollment_start_at' => optional($contest->enrollment_start_at)?->toIso8601String(),
            'enrollment_end_at' => optional($contest->enrollment_end_at)?->toIso8601String(),
            'voting_start_at' => optional($contest->voting_start_at)?->toIso8601String(),
            'voting_end_at' => optional($contest->voting_end_at)?->toIso8601String(),
            'winner_user_id' => $contest->winner_user_id !== null ? (int) $contest->winner_user_id : null,
            'winner_team_id' => $contest->winner_team_id !== null ? (int) $contest->winner_team_id : null,
            'is_admin_contest' => (int) ($contest->creator_user_id ?? 0) > 0
                ? in_array((string) optional(User::query()->find($contest->creator_user_id))->role, ['admin', 'moderator'], true)
                : false,
            'reward_amount' => (float) ($contest->prize_pool ?? 0),
            'has_reward' => (float) ($contest->prize_pool ?? 0) > 0,
            'can_join' => $contest->contest_type === 'city' ? $this->isEnrollmentWindowOpen($contest) : null,
            'is_participant' => $viewerUserId !== null
                ? ContestParticipant::query()->where('contest_id', $contest->id)->where('user_id', $viewerUserId)->where('status', 'approved')->exists()
                : null,
            'participants_ranking' => $participants->map(fn (ContestParticipant $participant) => [
                'user_id' => (int) $participant->user_id,
                'username' => $participant->user?->username,
                'avatar_url' => stylebite_asset_url($participant->user?->avatar_url),
                'score' => $participant->total_score !== null ? (float) $participant->total_score : 0.0,
                'rank_position' => $participant->rank_position !== null ? (int) $participant->rank_position : null,
                'seed' => $participant->rank_position !== null ? (int) $participant->rank_position : null,
            ])->values(),
            'team_ranking' => $teamRankings,
            'my_participant' => $myParticipant,
            'my_team' => $myTeam,
            'submissions' => $includeDetails
                ? $submissions->map(fn (ContestSubmission $submission) => $this->submissionPayload($submission))->values()
                : [],
        ];
    }

    private function cityJoinableUsers(Contest $contest): array
    {
        if ($contest->contest_type !== 'city') {
            return [];
        }

        $joinedCount = ContestParticipant::query()->where('contest_id', $contest->id)->where('status', 'approved')->count();
        $hasCapacity = $contest->max_participants === null || $joinedCount < (int) $contest->max_participants;

        if (! $this->isEnrollmentWindowOpen($contest) || ! $hasCapacity) {
            return [];
        }

        $joinedUserIds = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->pluck('user_id');

        return User::query()
            ->with('profile')
            ->whereNotIn('id', $joinedUserIds)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'username', 'full_name', 'avatar_url'])
            ->map(fn (User $user) => [
                'user_id' => (int) $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'city' => $user->profile?->city,
            ])
            ->values()
            ->all();
    }

    private function notifyOneVsOneInvitees(Contest $contest, int $senderUserId, array $receiverIds): void
    {
        $sender = User::query()->find($senderUserId);
        $actorName = $sender?->full_name ?: $sender?->username ?: 'A user';

        foreach (collect($receiverIds)->map(fn ($id) => (int) $id)->unique() as $receiverId) {
            if ($receiverId === $senderUserId) {
                continue;
            }

            stylebite_notify_user(
                recipientUserId: $receiverId,
                actorUserId: $senderUserId,
                type: 'contest',
                entityType: 'contest',
                entityId: (int) $contest->id,
                title: '1v1 contest invite',
                body: $actorName.' invited you to "'.$contest->title.'".',
                actionUrl: '/contests/'.$contest->id,
                image: $contest->cover_image_url
            );
        }
    }

    private function notifyCityContestUsers(Contest $contest, int $actorUserId, array $targetCities): void
    {
        $cities = collect($targetCities)
            ->map(fn ($city) => trim((string) $city))
            ->filter()
            ->unique(fn ($city) => Str::lower($city))
            ->values();

        if ($cities->isEmpty()) {
            return;
        }

        $users = User::query()
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->whereIn(DB::raw('LOWER(profiles.city)'), $cities->map(fn ($city) => Str::lower($city))->all())
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->select('users.id')
            ->get();

        foreach ($users as $userRow) {
            $recipientUserId = (int) $userRow->id;
            if ($recipientUserId === $actorUserId) {
                continue;
            }

            stylebite_notify_user(
                recipientUserId: $recipientUserId,
                actorUserId: $actorUserId,
                type: 'contest',
                entityType: 'contest',
                entityId: (int) $contest->id,
                title: 'New city contest',
                body: 'A new City vs City contest "'.$contest->title.'" is open for your city.',
                actionUrl: '/contests/'.$contest->id,
                image: $contest->cover_image_url
            );
        }
    }

    private function invitationStatusMessage(string $status): string
    {
        return match ($status) {
            'pending' => 'Waiting for your response.',
            'accepted' => 'Accepted successfully.',
            'rejected' => 'You rejected this invitation.',
            'cancelled' => 'Another user joined first, so this invite was closed.',
            default => 'Invitation updated.',
        };
    }

    private function uniqueContestSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? Str::limit($base, 100, '') : 'contest';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Contest::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function error(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
        ], $status);
    }
}
