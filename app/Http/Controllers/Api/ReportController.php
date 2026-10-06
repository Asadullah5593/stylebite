<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Contest;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    private const DESCRIPTION_MAX_LENGTH = 1000;

    private const HOURLY_LIMIT = 20;

    private const REASONS = [
        'spam' => 'Spam or ads',
        'harassment' => 'Harassment or bullying',
        'hate' => 'Hate speech',
        'nudity' => 'Nudity or sexual content',
        'violence' => 'Violence or dangerous acts',
        'copyright' => 'Intellectual property',
        'fake' => 'Scam or fake information',
        'other' => 'Something else',
    ];

    public function meta(): JsonResponse
    {
        $reasons = collect(self::REASONS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'Report reasons fetched successfully.',
            'reasons' => $reasons,
            'description_max_length' => self::DESCRIPTION_MAX_LENGTH,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;

        $paginator = Report::query()
            ->where('reporter_user_id', $request->user()->id)
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Reports fetched successfully.',
            'reports' => $paginator->getCollection()->map(fn (Report $report) => [
                'id' => $report->id,
                'target_type' => $report->target_type,
                'target_id' => (int) $report->target_id,
                'reason' => $report->reason,
                'description' => $report->description,
                'status' => $report->status,
                'created_at' => optional($report->created_at)?->toIso8601String(),
                'reviewed_at' => optional($report->reviewed_at)?->toIso8601String(),
            ])->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'string', 'in:user,post,comment,reply,message,contest'],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:spam,harassment,hate,nudity,violence,copyright,fake,other'],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX_LENGTH],
        ]);

        $reporter = $request->user();
        $ownerId = $this->ownerIdFor($validated['target_type'], (int) $validated['target_id']);

        if ($ownerId === null) {
            return response()->json([
                'status_code' => 0,
                'message' => 'This content is no longer available.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($ownerId === (int) $reporter->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot report your own content.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hourKey = 'reports:hour:'.$reporter->id;
        $hourlyCount = (int) Cache::get($hourKey, 0);
        if ($hourlyCount >= self::HOURLY_LIMIT) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You have reached the report limit. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $existing = Report::query()
            ->where('reporter_user_id', $reporter->id)
            ->where('target_type', $validated['target_type'])
            ->where('target_id', $validated['target_id'])
            ->whereIn('status', ['open', 'under_review'])
            ->first();

        if ($existing) {
            return response()->json([
                'status_code' => 1,
                'message' => 'You already reported this.',
                'already_reported' => true,
                'report' => [
                    'id' => $existing->id,
                    'status' => $existing->status,
                ],
            ]);
        }

        $report = Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'open',
        ]);

        Cache::put($hourKey, $hourlyCount + 1, now()->addHour());
        $this->flagTarget($validated['target_type'], (int) $validated['target_id']);

        return response()->json([
            'status_code' => 1,
            'message' => 'Report submitted successfully.',
            'already_reported' => false,
            'report' => [
                'id' => $report->id,
                'target_type' => $report->target_type,
                'target_id' => (int) $report->target_id,
                'reason' => $report->reason,
                'status' => $report->status,
                'created_at' => optional($report->created_at)?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    private function ownerIdFor(string $type, int $id): ?int
    {
        $ownerId = match ($type) {
            'user' => User::query()->whereKey($id)->value('id'),
            'post' => Post::query()->whereKey($id)->value('user_id'),
            'comment' => Comment::query()->whereKey($id)->value('user_id'),
            'reply' => CommentReply::query()->whereKey($id)->value('user_id'),
            'message' => Message::query()->whereKey($id)->value('sender_user_id'),
            'contest' => Contest::query()->whereKey($id)->value('creator_user_id'),
            default => null,
        };

        return $ownerId === null ? null : (int) $ownerId;
    }

    private function flagTarget(string $type, int $id): void
    {
        match ($type) {
            'post' => Post::query()->whereKey($id)->update([
                'is_reported' => true,
                'moderation_status' => 'flagged',
            ]),
            'comment' => Comment::query()->whereKey($id)->update([
                'is_reported' => true,
                'moderation_status' => 'flagged',
            ]),
            'reply' => CommentReply::query()->whereKey($id)->update([
                'is_reported' => true,
                'moderation_status' => 'flagged',
            ]),
            'contest' => Contest::query()->whereKey($id)->update([
                'is_reported' => true,
            ]),
            default => null,
        };
    }
}
