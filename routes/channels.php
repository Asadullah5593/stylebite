<?php

use App\Models\ConversationMember;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Two channels drive chat realtime:
|
|  presence-conversation.{id}  one open chat thread. Presence membership gives
|                              us "who is in this chat right now" for free, and
|                              client events on it carry typing indicators
|                              without touching the server.
|
|  private-user.{id}           everything the user needs while NOT inside a
|                              thread: chat list reordering and unread badges.
|
*/

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    $member = ConversationMember::query()
        ->where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->first();

    if (! $member) {
        return false;
    }

    $otherMemberIds = ConversationMember::query()
        ->where('conversation_id', $conversationId)
        ->where('user_id', '!=', $user->id)
        ->pluck('user_id');

    // A block must also cut the realtime channel, not just the REST endpoints.
    $isBlocked = UserBlock::query()
        ->where(function ($query) use ($user, $otherMemberIds) {
            $query->where('blocker_user_id', $user->id)->whereIn('blocked_user_id', $otherMemberIds);
        })
        ->orWhere(function ($query) use ($user, $otherMemberIds) {
            $query->whereIn('blocker_user_id', $otherMemberIds)->where('blocked_user_id', $user->id);
        })
        ->exists();

    if ($isBlocked) {
        return false;
    }

    $user->loadMissing('profile');

    return [
        'id' => (int) $user->id,
        'username' => $user->username,
        'name' => $user->profile?->display_name ?? $user->full_name,
        'image' => stylebite_asset_url($user->avatar_url),
    ];
});

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});
