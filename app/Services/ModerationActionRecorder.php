<?php

namespace App\Services;

use App\Models\ModerationAction;
use App\Models\User;

/**
 * Writes content-moderation decisions into `moderation_actions`.
 *
 * Per the reason-persistence decision, anything with a moderatable target goes
 * here rather than only into the activity log: the table is already polymorphic
 * with a `reason` column, and it is what the Moderation → Actions screen reads.
 * Before this, takedowns performed from the content lists left no row there at
 * all — only the report queue wrote to it — so the moderation history was
 * missing most of the decisions actually being made.
 */
class ModerationActionRecorder
{
    /**
     * Content moderation states mapped onto the action enum.
     */
    private const ACTION_FOR_MODERATION_STATUS = [
        'clean' => 'restore',
        'flagged' => 'hide',
        'restricted' => 'restrict',
        'blocked' => 'remove',
    ];

    public function record(
        string $targetType,
        int $targetId,
        string $action,
        ?string $reason,
        ?User $moderator = null
    ): ModerationAction {
        return ModerationAction::create([
            'moderator_user_id' => $moderator?->id ?? auth()->id(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Convenience for the content lists, which express a decision as a
     * moderation status rather than an action verb.
     */
    public function recordStatusChange(
        string $targetType,
        int $targetId,
        string $moderationStatus,
        ?string $reason,
        ?User $moderator = null
    ): ?ModerationAction {
        $action = self::ACTION_FOR_MODERATION_STATUS[$moderationStatus] ?? null;

        if ($action === null) {
            return null;
        }

        return $this->record($targetType, $targetId, $action, $reason, $moderator);
    }
}
