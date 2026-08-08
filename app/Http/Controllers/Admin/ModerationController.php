<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Contest;
use App\Models\ActivityLog;
use App\Models\ModerationAction;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\UserModerationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends Controller
{
    private const REPORT_STATUSES = ['open', 'under_review', 'resolved', 'rejected'];
    private const TARGET_ACTIONS = ['ban', 'hide', 'restrict', 'restore'];
    // Accounts are banned/suspended/restored — hide and restrict only make
    // sense for content, and used to be logged against users without doing
    // anything at all.
    private const USER_TARGET_ACTIONS = ['ban', 'suspend', 'restore'];
    private const ACTION_TYPES_WITH_EXPIRY = ['warn', 'restrict', 'ban', 'suspend'];

    public function reports(Request $request): View
    {
        $reports = Report::query()
            ->with(['reporter:id,username,full_name', 'reviewer:id,username,full_name'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('resolution_notes', 'like', "%{$search}%")
                        ->orWhereHas('reporter', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('reviewer', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('reason'), fn ($query) => $query->where('reason', $request->string('reason')))
            ->when($request->filled('target_type'), fn ($query) => $query->where('target_type', $request->string('target_type')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $reports->getCollection()->transform(function (Report $report) {
            $report->setRelation('targetModel', $this->resolveReportTarget($report));

            return $report;
        });

        return view('admin.moderation.ReportsPage', compact('reports'));
    }

    public function assign(Report $report): RedirectResponse
    {
        $report->update([
            'status' => 'under_review',
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->logActivity('report_assigned', 'report', $report->id, [
            'status' => 'under_review',
            'target_type' => $report->target_type,
            'target_id' => $report->target_id,
        ]);

        return back()->with('status', "Report #{$report->id} assigned to you.");
    }

    public function updateReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::REPORT_STATUSES)],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $data['status'];
        $notes = filled($data['resolution_notes'] ?? null) ? $data['resolution_notes'] : null;

        $report->fill([
            'status' => $status,
            'resolution_notes' => $notes,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        $this->logActivity('report_updated', 'report', $report->id, [
            'status' => $status,
            'resolution_notes' => $notes,
        ]);

        return back()->with('status', "Report #{$report->id} updated to ".str($status)->replace('_', ' ')->title().'.');
    }

    public function updateTarget(Request $request, Report $report): RedirectResponse
    {
        $isUserTarget = $report->target_type === 'user';

        // Preset suspension lengths arrive as duration_hours; fold them into
        // suspended_until so validation deals with a single field.
        if ($request->input('action') === 'suspend'
            && ! $request->filled('suspended_until')
            && is_numeric($request->input('duration_hours'))) {
            $request->merge([
                'suspended_until' => now()
                    ->addHours(min(8760, max(1, (int) $request->input('duration_hours'))))
                    ->toDateTimeString(),
            ]);
        }

        $data = $request->validate([
            'action' => ['required', Rule::in($isUserTarget ? self::USER_TARGET_ACTIONS : self::TARGET_ACTIONS)],
            'reason' => ['nullable', 'string', 'max:500'],
            'suspended_until' => ['required_if:action,suspend', 'nullable', 'date', 'after:now'],
        ], [
            'suspended_until.required_if' => 'Please choose when the suspension should end.',
            'suspended_until.after' => 'The suspension end time must be in the future.',
        ]);

        $target = $this->resolveReportTarget($report);

        if (! $target || ! $this->supportsModerationAction($report->target_type)) {
            return back()->with('status', "Report #{$report->id} target action is not available yet.");
        }

        $action = $data['action'];
        // The admin's own words win; otherwise inherit the report context.
        $reason = filled($data['reason'] ?? null)
            ? $data['reason']
            : ($report->resolution_notes ?: $report->description ?: $report->reason);

        if ($isUserTarget) {
            if ($target->id === auth()->id()) {
                return back()->with('status', 'You cannot apply this action to your own account.');
            }

            // The service writes the moderation_actions row, revokes sessions,
            // and logs the lifecycle change — nothing to persist here.
            $service = app(UserModerationService::class);

            match ($action) {
                'ban' => $service->ban($target, $request->user(), $reason),
                'suspend' => $service->suspend($target, $request->user(), $reason, CarbonImmutable::parse($data['suspended_until'])),
                default => $service->reinstate($target, $request->user(), $reason),
            };
        } else {
            $this->applyTargetAction($report->target_type, $target, $action);

            if ($this->canPersistModerationAction($report->target_type)) {
                ModerationAction::create([
                    'moderator_user_id' => auth()->id(),
                    'target_type' => $report->target_type,
                    'target_id' => $report->target_id,
                    'action' => $action,
                    'reason' => $reason,
                    'created_at' => now(),
                ]);
            }
        }

        if ($report->status === 'open') {
            $report->update([
                'status' => 'under_review',
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        }

        $this->logActivity('moderation_target_updated', $report->target_type, $report->target_id, [
            'report_id' => $report->id,
            'action' => $action,
            'reason' => $reason,
        ]);

        return back()->with('status', str($report->target_type)->title().' #'.$report->target_id.' updated with '.str($action)->title().' action.');
    }

    public function updateActionExpiry(Request $request, ModerationAction $moderationAction): RedirectResponse
    {
        $data = $request->validate([
            'expires_at' => ['nullable', 'date'],
        ]);

        $moderationAction->update([
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        // Re-timing a suspension from the actions log moves the real window,
        // not just the display row. Clearing it makes the suspension indefinite.
        if ($moderationAction->action === 'suspend' && $moderationAction->target_type === 'user') {
            $user = User::query()->find($moderationAction->target_id);

            if ($user && $user->status === 'suspended') {
                $user->forceFill([
                    'suspended_until' => isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
                ])->save();
            }
        }

        $this->logActivity('moderation_action_expiry_updated', 'moderation_action', $moderationAction->id, [
            'target_type' => $moderationAction->target_type,
            'target_id' => $moderationAction->target_id,
            'action' => $moderationAction->action,
            'expires_at' => $moderationAction->expires_at?->toDateTimeString(),
        ]);

        return back()->with('status', "Moderation action #{$moderationAction->id} expiry updated successfully.");
    }

    public function actions(Request $request): View
    {
        $actions = ModerationAction::query()
            ->with('moderator:id,username,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('reason', 'like', "%{$search}%")
                        ->orWhere('target_type', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('moderator', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $actions->getCollection()->transform(function (ModerationAction $action) {
            $action->setRelation('targetModel', $this->resolveActionTarget($action));

            return $action;
        });

        return view('admin.moderation.ModerationActionsPage', compact('actions'));
    }

    public static function tabCounts(): array
    {
        return [
            'reports' => Report::count(),
            'actions' => ModerationAction::count(),
        ];
    }

    private function resolveReportTarget(Report $report): User|Post|Comment|CommentReply|Contest|Message|null
    {
        return match ($report->target_type) {
            'user' => User::query()->find($report->target_id),
            'post' => Post::query()->with('user:id,username,full_name')->find($report->target_id),
            'comment' => Comment::query()->with(['user:id,username,full_name', 'post:id,caption'])->find($report->target_id),
            'reply' => CommentReply::query()->with(['user:id,username,full_name', 'comment:id,body'])->find($report->target_id),
            'contest' => Contest::query()->with('creator:id,username,full_name')->find($report->target_id),
            'message' => Message::query()->with([
                'sender:id,username,full_name',
                'conversation:id,type,title',
                'attachments:id,message_id,media_type,file_url,thumbnail_url,mime_type',
            ])->find($report->target_id),
            default => null,
        };
    }

    private function supportsModerationAction(string $targetType): bool
    {
        return in_array($targetType, ['user', 'post', 'comment', 'reply', 'contest', 'message'], true);
    }

    public static function actionTypesWithExpiry(): array
    {
        return self::ACTION_TYPES_WITH_EXPIRY;
    }

    private function applyTargetAction(string $targetType, Post|Comment|CommentReply|Contest|Message $target, string $action): void
    {
        match ($targetType) {
            'post' => $this->applyContentAction($target, $action),
            'comment' => $this->applyContentAction($target, $action),
            'reply' => $this->applyContentAction($target, $action),
            'contest' => $this->applyContestAction($target, $action),
            'message' => $this->applyMessageAction($target, $action),
            default => null,
        };
    }

    private function applyContentAction(Post|Comment|CommentReply $target, string $action): void
    {
        if ($target instanceof Post) {
            $this->applyPostAction($target, $action);

            return;
        }

        match ($action) {
            'hide' => $target->fill([
                'status' => 'hidden',
                'moderation_status' => 'flagged',
                'is_blocked' => false,
            ]),
            'restrict', 'ban' => $target->fill([
                'status' => 'blocked',
                'moderation_status' => 'restricted',
                'is_blocked' => true,
            ]),
            'restore' => $target->fill([
                'status' => 'active',
                'moderation_status' => 'clean',
                'is_blocked' => false,
            ]),
            default => $target,
        };

        $target->is_reported = $action === 'restore' ? false : true;
        $target->save();
    }

    private function applyPostAction(Post $post, string $action): void
    {
        match ($action) {
            'hide' => $post->fill([
                'status' => 'under_review',
                'moderation_status' => 'flagged',
                'is_blocked' => false,
            ]),
            'restrict' => $post->fill([
                'status' => 'under_review',
                'moderation_status' => 'restricted',
                'is_blocked' => true,
            ]),
            'ban' => $post->fill([
                'status' => 'removed',
                'moderation_status' => 'blocked',
                'is_blocked' => true,
            ]),
            'restore' => $post->fill([
                'status' => 'published',
                'moderation_status' => 'clean',
                'is_blocked' => false,
            ]),
            default => $post,
        };

        $post->is_reported = $action === 'restore' ? false : true;
        $post->save();
    }

    private function applyContestAction(Contest $contest, string $action): void
    {
        match ($action) {
            'hide' => $contest->fill([
                'status' => 'archived',
                'is_blocked' => false,
            ]),
            'restrict' => $contest->fill([
                'status' => 'cancelled',
                'is_blocked' => true,
            ]),
            'ban' => $contest->fill([
                'status' => 'cancelled',
                'is_blocked' => true,
            ]),
            'restore' => $contest->fill([
                'status' => 'active',
                'is_blocked' => false,
            ]),
            default => $contest,
        };

        $contest->is_reported = $action === 'restore' ? false : true;
        $contest->save();
    }

    private function applyMessageAction(Message $message, string $action): void
    {
        match ($action) {
            'hide', 'restrict', 'ban' => $message->fill([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]),
            'restore' => $message->fill([
                'is_deleted' => false,
                'deleted_at' => null,
            ]),
            default => $message,
        };

        $message->save();
    }

    private function resolveActionTarget(ModerationAction $action): User|Post|Comment|CommentReply|Contest|Message|null
    {
        return match ($action->target_type) {
            'user' => User::query()->find($action->target_id),
            'post' => Post::query()->with('user:id,username,full_name')->find($action->target_id),
            'comment' => Comment::query()->with('user:id,username,full_name')->find($action->target_id),
            'reply' => CommentReply::query()->with('user:id,username,full_name')->find($action->target_id),
            'contest' => Contest::query()->with('creator:id,username,full_name')->find($action->target_id),
            'message' => Message::query()->with([
                'sender:id,username,full_name',
                'conversation:id,type,title',
                'attachments:id,message_id,media_type,file_url,thumbnail_url,mime_type',
            ])->find($action->target_id),
            default => null,
        };
    }

    private function canPersistModerationAction(string $targetType): bool
    {
        return in_array($targetType, ['user', 'post', 'comment', 'reply', 'contest', 'message'], true);
    }

    private function logActivity(string $eventName, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
