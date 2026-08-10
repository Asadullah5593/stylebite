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
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a user report abusive content or accounts.
 *
 * Until this existed the admin moderation queue had no inbound source at all —
 * a full review UI over a table only tests wrote to. Bug reports deliberately
 * do NOT come here: they need a conversation and device metadata, so they are a
 * support ticket category instead.
 */
class ReportController extends Controller
{
    /**
     * Target types the admin queue can actually resolve and act on. Anything
     * outside this list would create a report nobody could action, so it is
     * rejected rather than silently accepted.
     */
    private const TARGET_TYPES = ['user', 'post', 'comment', 'reply', 'message', 'contest'];

    private const REASONS = ['spam', 'harassment', 'hate', 'nudity', 'violence', 'copyright', 'fake', 'other'];

    /** Where a report should flip the target's is_reported flag. */
    private const REPORT_FLAG_TABLES = [
        'post' => 'posts',
        'comment' => 'comments',
        'reply' => 'comment_replies',
        'contest' => 'contests',
    ];

    public function meta(): JsonResponse
    {
        return response()->json([
            'status_code' => 1,
            'message' => 'Report options fetched successfully.',
            'target_types' => self::TARGET_TYPES,
            'reasons' => array_map(fn (string $reason) => [
                'value' => $reason,
                'label' => str($reason)->replace('_', ' ')->title()->value(),
            ], self::REASONS),
            'description_max_length' => 1000,
        ]);
    }

    #[BodyParameter('target_type', description: 'One of: user, post, comment, reply, message, contest.', required: true, type: 'string', example: 'post')]
    #[BodyParameter('target_id', required: true, type: 'integer', example: 42)]
    #[BodyParameter('reason', description: 'One of: spam, harassment, hate, nudity, violence, copyright, fake, other.', required: true, type: 'string', example: 'harassment')]
    #[BodyParameter('description', description: 'Optional detail from the reporter.', type: 'string', example: 'Repeated abusive comments on my post.')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'string', Rule::in(self::TARGET_TYPES)],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', Rule::in(self::REASONS)],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'target_type.in' => 'That kind of content cannot be reported.',
            'reason.in' => 'Please choose a valid reason.',
            'description.max' => 'Please keep the description under 1000 characters.',
        ]);

        $reporter = $request->user();
        $target = $this->resolveTarget($validated['target_type'], (int) $validated['target_id']);

        if (! $target) {
            return response()->json([
                'status_code' => 0,
                'message' => 'That content no longer exists.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($this->ownsTarget($reporter->id, $validated['target_type'], $target)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot report your own content.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // A message id is guessable, so only a participant of that conversation
        // may report it — otherwise anyone could flag private messages.
        if ($validated['target_type'] === 'message' && ! $this->canSeeMessage($reporter->id, $target)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'That content no longer exists.',
            ], Response::HTTP_NOT_FOUND);
        }

        // One open report per person per target: re-reporting the same thing
        // does not help moderators and is the obvious spam vector.
        $existing = Report::query()
            ->where('reporter_user_id', $reporter->id)
            ->where('target_type', $validated['target_type'])
            ->where('target_id', $validated['target_id'])
            ->whereIn('status', ['open', 'under_review'])
            ->first();

        if ($existing) {
            return response()->json([
                'status_code' => 1,
                'message' => 'You have already reported this. Our team is reviewing it.',
                'report' => $this->reportPayload($existing),
                'already_reported' => true,
            ]);
        }

        $report = DB::transaction(function () use ($validated, $reporter) {
            $report = Report::create([
                'reporter_user_id' => $reporter->id,
                'target_type' => $validated['target_type'],
                'target_id' => $validated['target_id'],
                'reason' => $validated['reason'],
                'description' => filled($validated['description'] ?? null) ? $validated['description'] : null,
                'status' => 'open',
            ]);

            // Surfaces the item as reported in the admin content lists. Not every
            // target type has the column, hence the map.
            if ($table = self::REPORT_FLAG_TABLES[$validated['target_type']] ?? null) {
                DB::table($table)->where('id', $validated['target_id'])->update(['is_reported' => true]);
            }

            return $report;
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Thanks — our team will review this.',
            'report' => $this->reportPayload($report),
            'already_reported' => false,
        ], Response::HTTP_CREATED);
    }

    /**
     * The reporter's own history, so the app can show that something was acted
     * on rather than leaving reports feeling ignored.
     */
    public function mine(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('reporter_user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'status_code' => 1,
            'message' => 'Reports fetched successfully.',
            'reports' => $reports->getCollection()->map(fn (Report $report) => $this->reportPayload($report))->values(),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
                'has_more_pages' => $reports->hasMorePages(),
            ],
        ]);
    }

    private function resolveTarget(string $type, int $id): ?Model
    {
        return match ($type) {
            'user' => User::query()->find($id),
            'post' => Post::query()->find($id),
            'comment' => Comment::query()->find($id),
            'reply' => CommentReply::query()->find($id),
            'contest' => Contest::query()->find($id),
            'message' => Message::query()->find($id),
            default => null,
        };
    }

    private function ownsTarget(int $reporterId, string $type, Model $target): bool
    {
        return match ($type) {
            'user' => (int) $target->id === $reporterId,
            'contest' => (int) $target->creator_user_id === $reporterId,
            'message' => (int) $target->sender_user_id === $reporterId,
            default => (int) $target->user_id === $reporterId,
        };
    }

    private function canSeeMessage(int $reporterId, Message $message): bool
    {
        return DB::table('conversation_members')
            ->where('conversation_id', $message->conversation_id)
            ->where('user_id', $reporterId)
            ->exists();
    }

    private function reportPayload(Report $report): array
    {
        return [
            'id' => $report->id,
            'target_type' => $report->target_type,
            'target_id' => (int) $report->target_id,
            'reason' => $report->reason,
            'description' => $report->description,
            'status' => $report->status,
            'created_at' => $report->created_at?->toISOString(),
            'reviewed_at' => $report->reviewed_at?->toISOString(),
        ];
    }
}
