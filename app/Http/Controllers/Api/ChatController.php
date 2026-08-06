<?php

namespace App\Http\Controllers\Api;

use App\Events\Chat\ChatListUpdated;
use App\Events\Chat\MessagesDelivered;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessagesRead;
use App\Events\Chat\MessagingStateChanged;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    /**
     * A self-reported "online" flag is only trusted for this long. The client
     * refreshes presence while the app is foregrounded; without this window a
     * force-quit would leave the user online forever.
     */
    private const PRESENCE_TTL_MINUTES = 2;

    /**
     * How many messages one person may send before the other has replied at all.
     * Once a reply lands the cap stops applying for the rest of the conversation.
     */
    private const MAX_UNANSWERED_MESSAGES = 2;

    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
        ]);

        $viewer = $request->user();
        $target = User::query()->with('profile')->where('username', $validated['username'])->firstOrFail();

        if ((int) $viewer->id === (int) $target->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot start a chat with yourself.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $blockState = $this->blockStateFor((int) $viewer->id, (int) $target->id);

        if ($blockState['is_blocked_by_me'] || $blockState['is_blocked_by_other']) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot start a chat with this user.',
                ...$blockState,
            ], Response::HTTP_FORBIDDEN);
        }

        $conversation = DB::transaction(function () use ($viewer, $target): Conversation {
            $conversation = Conversation::query()
                ->where('type', 'direct')
                ->whereExists(function ($query) use ($viewer) {
                    $query->selectRaw('1')
                        ->from('conversation_members as cm1')
                        ->whereColumn('cm1.conversation_id', 'conversations.id')
                        ->where('cm1.user_id', $viewer->id);
                })
                ->whereExists(function ($query) use ($target) {
                    $query->selectRaw('1')
                        ->from('conversation_members as cm2')
                        ->whereColumn('cm2.conversation_id', 'conversations.id')
                        ->where('cm2.user_id', $target->id);
                })
                ->whereRaw('(select count(*) from conversation_members where conversation_members.conversation_id = conversations.id) = 2')
                ->first();

            if ($conversation) {
                return $conversation;
            }

            $conversation = Conversation::query()->create([
                'type' => 'direct',
                'created_by_user_id' => $viewer->id,
            ]);

            ConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $viewer->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            ConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $target->id,
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $conversation;
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Chat initialized successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage']), $this->unreadCountFor($viewer->id, $conversation->id)),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'skip' => ['nullable', 'integer', 'min:0'],
        ]);

        $viewer = $request->user();
        $perPage = 10;
        $skip = (int) ($validated['skip'] ?? $validated['offset'] ?? 0);
        $page = (int) ($validated['page'] ?? (intdiv($skip, $perPage) + 1));
        $search = trim((string) ($validated['search'] ?? ''));

        $baseQuery = Conversation::query()
            ->with([
                'members.user.profile',
                'lastMessage.sender',
            ])
            ->where('type', 'direct')
            // A conversation only becomes visible once someone actually says
            // something. Tapping a profile creates the thread, and an empty one
            // has nothing to show in a list.
            ->whereNotNull('last_message_id')
            ->whereHas('members', function (Builder $query) use ($viewer): void {
                $query->where('user_id', $viewer->id)->where('status', 'active');
            })
            ->when($search !== '', function (Builder $query) use ($viewer, $search): void {
                $query->whereHas('members.user', function (Builder $memberQuery) use ($viewer, $search): void {
                    $memberQuery
                        ->where('users.id', '!=', $viewer->id)
                        ->where(function (Builder $whereQuery) use ($search): void {
                            $whereQuery
                                ->where('users.username', 'like', '%'.$search.'%')
                                ->orWhere('users.full_name', 'like', '%'.$search.'%')
                                ->orWhereHas('profile', function (Builder $profileQuery) use ($search): void {
                                    $profileQuery->where('display_name', 'like', '%'.$search.'%');
                                });
                        });
                });
            });

        $total = (clone $baseQuery)->count();

        $chats = $baseQuery
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->skip($skip)
            ->take($perPage)
            ->get();

        $unreadCounts = $this->unreadCountsFor($viewer->id, $chats->pluck('id')->all());

        $otherUserIds = $chats
            ->map(fn (Conversation $conversation) => $this->otherMember($conversation, (int) $viewer->id)?->user_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $blockStates = $this->blockStatesFor((int) $viewer->id, $otherUserIds);

        return response()->json([
            'status_code' => 1,
            'message' => 'Chats fetched successfully.',
            'chats' => $chats->map(fn (Conversation $conversation) => $this->conversationPayload(
                $viewer->id,
                $conversation,
                (int) ($unreadCounts[$conversation->id] ?? 0),
                $blockStates[(int) ($this->otherMember($conversation, (int) $viewer->id)?->user_id ?? 0)] ?? null
            )),
            'total_unread_count' => $this->totalUnreadCountFor($viewer->id),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'offset' => $skip,
                'skip' => $skip,
            ],
        ]);
    }

    public function messages(Request $request, string $username): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'skip' => ['nullable', 'integer', 'min:0'],
        ]);

        $viewer = $request->user();
        $target = User::query()->with('profile')->where('username', $username)->firstOrFail();
        $conversation = $this->findDirectConversation($viewer->id, $target->id);

        if (! $conversation) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Messages fetched successfully.',
                'chat' => null,
                'messages' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => 10,
                    'current_page' => 1,
                    'last_page' => 0,
                    'offset' => 0,
                    'skip' => 0,
                ],
            ]);
        }

        $perPage = 10;
        $skip = (int) ($validated['skip'] ?? $validated['offset'] ?? 0);
        $page = (int) ($validated['page'] ?? (intdiv($skip, $perPage) + 1));

        $baseQuery = Message::query()
            ->with('sender.profile')
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false);

        $total = (clone $baseQuery)->count();

        $messages = $baseQuery
            ->latest('id')
            ->skip($skip)
            ->take($perPage)
            ->get()
            ->reverse()
            ->values();

        // Fetching a conversation proves the recipient's device now holds these
        // messages, which is exactly what "delivered" means.
        $this->markIncomingAsDelivered($conversation->id, $viewer->id, $messages);

        $conversation = $conversation->fresh(['members.user.profile', 'lastMessage']);
        $otherLastReadId = $this->otherMemberLastReadMessageId($conversation, $viewer->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Messages fetched successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation, $this->unreadCountFor($viewer->id, $conversation->id)),
            'messages' => $messages->map(fn (Message $message) => $this->messagePayload($viewer->id, $message, $otherLastReadId)),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'offset' => $skip,
                'skip' => $skip,
            ],
        ]);
    }

    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $viewer = $request->user();

        $conversation = Conversation::query()
            ->with(['members.user.profile'])
            ->where('id', $conversationId)
            ->where('type', 'direct')
            ->firstOrFail();

        $isMember = $conversation->members->contains(fn (ConversationMember $member) => (int) $member->user_id === (int) $viewer->id && $member->status === 'active');

        if (! $isMember) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not allowed to send message in this chat.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($conversation->messaging_stopped_at !== null) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Messaging has been stopped for this chat.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $recipientMember = $this->otherMember($conversation, (int) $viewer->id);

        if ($recipientMember) {
            $blockState = $this->blockStateFor((int) $viewer->id, (int) $recipientMember->user_id);

            if ($blockState['is_blocked_by_me'] || $blockState['is_blocked_by_other']) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'You can no longer send messages in this chat.',
                    ...$blockState,
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Opening-message limit: until the other person has replied even once,
        // a sender is capped. Two counts and a min() in one pass, rather than a
        // count followed by a distinct scan of every message in the thread.
        $stats = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->selectRaw('count(*) as total_messages, count(distinct sender_user_id) as sender_count, min(sender_user_id) as only_sender_id')
            ->first();

        $isUnansweredMonologue = (int) $stats->sender_count === 1
            && (int) $stats->only_sender_id === (int) $viewer->id;

        if ($isUnansweredMonologue && (int) $stats->total_messages >= self::MAX_UNANSWERED_MESSAGES) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Wait for reply before sending another message.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = DB::transaction(function () use ($conversation, $viewer, $validated): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $viewer->id,
                'message_type' => 'text',
                'body' => $validated['body'],
                'sent_at' => now(),
            ]);

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->sent_at,
            ])->save();

            ConversationMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $viewer->id)
                ->update([
                    'last_read_message_id' => $message->id,
                    'last_read_at' => now(),
                ]);

            return $message;
        });

        $message = $message->fresh('sender.profile');

        // Realtime first, then the push. The push does synchronous HTTPS calls to
        // Google, and an in-app recipient should not wait behind those.
        stylebite_broadcast(new MessageSent($conversation->id, $this->broadcastMessagePayload($message)));

        if ($recipientMember) {
            $this->broadcastChatListUpdate((int) $recipientMember->user_id, $conversation);
        }

        $recipient = $recipientMember?->user;

        if ($recipient) {
            stylebite_notify_user(
                $recipient->id,
                $viewer->id,
                'message',
                'message',
                $message->id,
                $viewer->full_name ?: $viewer->username,
                (string) $message->body,
                '/chat/'.$conversation->id,
                $viewer->avatar_url
            );
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Message sent successfully.',
            'message_data' => $this->messagePayload(
                $viewer->id,
                $message,
                $recipientMember?->last_read_message_id !== null ? (int) $recipientMember->last_read_message_id : null
            ),
        ]);
    }

    public function stopMessaging(Request $request, int $conversationId): JsonResponse
    {
        return $this->setMessagingState($request, $conversationId, true);
    }

    public function resumeMessaging(Request $request, int $conversationId): JsonResponse
    {
        return $this->setMessagingState($request, $conversationId, false);
    }

    public function updatePresence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $user->forceFill([
            'is_online' => (bool) $validated['is_online'],
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Presence updated successfully.',
            'presence' => [
                'is_online' => $this->resolveIsOnline($user),
                'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
            ],
        ]);
    }

    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'up_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $viewer = $request->user();

        $conversation = Conversation::query()
            ->with('members')
            ->where('id', $conversationId)
            ->where('type', 'direct')
            ->firstOrFail();

        $member = $conversation->members
            ->first(fn (ConversationMember $m) => (int) $m->user_id === (int) $viewer->id && $m->status === 'active');

        if (! $member) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not allowed to update this chat.',
            ], Response::HTTP_FORBIDDEN);
        }

        $upToMessageId = $validated['up_to_message_id'] ?? null;

        $unreadQuery = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_user_id', '!=', $viewer->id)
            ->where('is_deleted', false)
            ->when($upToMessageId !== null, fn (Builder $q) => $q->where('id', '<=', $upToMessageId));

        $unreadMessageIds = (clone $unreadQuery)
            ->whereDoesntHave('reads', fn (Builder $q) => $q->where('user_id', $viewer->id))
            ->orderBy('id')
            ->pluck('id');

        $highestReadId = (int) max(
            (clone $unreadQuery)->max('id') ?? 0,
            (int) ($member->last_read_message_id ?? 0)
        );

        DB::transaction(function () use ($unreadMessageIds, $viewer, $conversation, $highestReadId): void {
            $now = now();

            if ($unreadMessageIds->isNotEmpty()) {
                MessageRead::query()->insertOrIgnore(
                    $unreadMessageIds
                        ->map(fn ($messageId) => [
                            'message_id' => $messageId,
                            'user_id' => $viewer->id,
                            'read_at' => $now,
                        ])
                        ->all()
                );
            }

            if ($highestReadId > 0) {
                ConversationMember::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $viewer->id)
                    ->update([
                        'last_read_message_id' => $highestReadId,
                        'last_read_at' => $now,
                    ]);
            }
        });

        if ($unreadMessageIds->isNotEmpty()) {
            stylebite_broadcast(new MessagesRead(
                conversationId: (int) $conversation->id,
                readerUserId: (int) $viewer->id,
                lastReadMessageId: $highestReadId > 0 ? $highestReadId : null,
                messageIds: $unreadMessageIds->map(fn ($id) => (int) $id)->values()->all(),
                readAt: now()->toIso8601String(),
            ));

            $this->broadcastChatListUpdate((int) $viewer->id, $conversation);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Messages marked as read successfully.',
            'conversation_id' => $conversation->id,
            'last_read_message_id' => $highestReadId > 0 ? $highestReadId : null,
            'read_message_ids' => $unreadMessageIds->values(),
            'unread_count' => $this->unreadCountFor($viewer->id, $conversation->id),
            'total_unread_count' => $this->totalUnreadCountFor($viewer->id),
        ]);
    }

    /**
     * Recovery endpoint for the realtime client: returns everything that changed
     * after a known message id, so a dropped socket can catch up without
     * re-paginating the whole conversation.
     */
    public function sync(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'after_message_id' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $viewer = $request->user();
        $limit = (int) ($validated['limit'] ?? 100);

        $conversation = Conversation::query()
            ->with(['members.user.profile', 'lastMessage'])
            ->where('id', $conversationId)
            ->where('type', 'direct')
            ->firstOrFail();

        $isMember = $conversation->members
            ->contains(fn (ConversationMember $m) => (int) $m->user_id === (int) $viewer->id && $m->status === 'active');

        if (! $isMember) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not allowed to view this chat.',
            ], Response::HTTP_FORBIDDEN);
        }

        $messages = Message::query()
            ->with('sender.profile')
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->where('id', '>', (int) $validated['after_message_id'])
            ->orderBy('id')
            ->take($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->values();

        $this->markIncomingAsDelivered($conversation->id, $viewer->id, $messages);

        $otherLastReadId = $this->otherMemberLastReadMessageId($conversation, $viewer->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Messages synced successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation, $this->unreadCountFor($viewer->id, $conversation->id)),
            'messages' => $messages->map(fn (Message $message) => $this->messagePayload($viewer->id, $message, $otherLastReadId)),
            'has_more' => $hasMore,
            'cursor' => $messages->last()?->id ?? (int) $validated['after_message_id'],
        ]);
    }

    /**
     * Viewer-neutral copy of a message for the wire. It deliberately omits
     * `is_mine` and a viewer-relative `status` — a broadcast reaches both sides
     * of the conversation, so each client derives those from `sender_user_id`.
     *
     * @return array<string, mixed>
     */
    private function broadcastMessagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->profile?->display_name ?? $message->sender?->full_name,
            'body' => $message->body,
            'message_type' => $message->message_type,
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
            'delivered_at' => optional($message->delivered_at)?->toIso8601String(),
            'status' => $message->delivered_at !== null ? 'delivered' : 'sent',
        ];
    }

    private function broadcastChatListUpdate(int $userId, Conversation $conversation): void
    {
        $conversation = $conversation->fresh(['members.user.profile', 'lastMessage']);

        if (! $conversation) {
            return;
        }

        stylebite_broadcast(new ChatListUpdated(
            userId: $userId,
            chat: $this->conversationPayload($userId, $conversation, $this->unreadCountFor($userId, (int) $conversation->id)),
            totalUnreadCount: $this->totalUnreadCountFor($userId),
        ));
    }

    private function isBlockedBetween(int $userId, int $otherUserId): bool
    {
        $state = $this->blockStateFor($userId, $otherUserId);

        return $state['is_blocked_by_me'] || $state['is_blocked_by_other'];
    }

    /**
     * Which side of a block is which. The client needs to tell "I blocked them"
     * from "they blocked me" — the two produce very different UI — so both
     * directions are reported explicitly rather than as one boolean.
     *
     * @return array{is_blocked_by_me: bool, is_blocked_by_other: bool}
     */
    private function blockStateFor(int $viewerUserId, int $otherUserId): array
    {
        return $this->blockStatesFor($viewerUserId, [$otherUserId])[$otherUserId]
            ?? ['is_blocked_by_me' => false, 'is_blocked_by_other' => false];
    }

    /**
     * Block state for many counterparties in one query, so the chat list does
     * not run a block lookup per row.
     *
     * @param  array<int, int>  $otherUserIds
     * @return array<int, array{is_blocked_by_me: bool, is_blocked_by_other: bool}>
     */
    private function blockStatesFor(int $viewerUserId, array $otherUserIds): array
    {
        if ($otherUserIds === []) {
            return [];
        }

        $states = [];

        foreach ($otherUserIds as $otherUserId) {
            $states[(int) $otherUserId] = [
                'is_blocked_by_me' => false,
                'is_blocked_by_other' => false,
            ];
        }

        $rows = UserBlock::query()
            ->where(function (Builder $query) use ($viewerUserId, $otherUserIds): void {
                $query->where('blocker_user_id', $viewerUserId)->whereIn('blocked_user_id', $otherUserIds);
            })
            ->orWhere(function (Builder $query) use ($viewerUserId, $otherUserIds): void {
                $query->whereIn('blocker_user_id', $otherUserIds)->where('blocked_user_id', $viewerUserId);
            })
            ->get(['blocker_user_id', 'blocked_user_id']);

        foreach ($rows as $row) {
            if ((int) $row->blocker_user_id === $viewerUserId) {
                $states[(int) $row->blocked_user_id]['is_blocked_by_me'] = true;
            } else {
                $states[(int) $row->blocker_user_id]['is_blocked_by_other'] = true;
            }
        }

        return $states;
    }

    private function otherMember(Conversation $conversation, int $viewerUserId): ?ConversationMember
    {
        return $conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id !== $viewerUserId);
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function markIncomingAsDelivered(int $conversationId, int $viewerUserId, Collection $messages): void
    {
        $undelivered = $messages
            ->filter(fn (Message $message) => (int) $message->sender_user_id !== $viewerUserId && $message->delivered_at === null)
            ->pluck('id');

        if ($undelivered->isEmpty()) {
            return;
        }

        $now = now();

        Message::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('id', $undelivered)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => $now]);

        $messages
            ->whereIn('id', $undelivered->all())
            ->each(fn (Message $message) => $message->setAttribute('delivered_at', $now));

        stylebite_broadcast(new MessagesDelivered(
            conversationId: $conversationId,
            recipientUserId: $viewerUserId,
            messageIds: $undelivered->map(fn ($id) => (int) $id)->values()->all(),
            deliveredAt: $now->toIso8601String(),
        ));
    }

    private function otherMemberLastReadMessageId(Conversation $conversation, int $viewerUserId): ?int
    {
        $otherMember = $this->otherMember($conversation, $viewerUserId);

        return $otherMember?->last_read_message_id !== null
            ? (int) $otherMember->last_read_message_id
            : null;
    }

    private function unreadCountFor(int $viewerUserId, int $conversationId): int
    {
        return (int) ($this->unreadCountsFor($viewerUserId, [$conversationId])[$conversationId] ?? 0);
    }

    /**
     * Unread counts for many conversations in a single query, so the chat list
     * does not fan out into one count per row.
     *
     * @param  array<int, int>  $conversationIds
     * @return array<int, int>
     */
    private function unreadCountsFor(int $viewerUserId, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return $this->unreadCountQuery($viewerUserId)
            ->whereIn('messages.conversation_id', $conversationIds)
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id as conversation_id, count(*) as unread_count')
            ->pluck('unread_count', 'conversation_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function totalUnreadCountFor(int $viewerUserId): int
    {
        return (int) $this->unreadCountQuery($viewerUserId)->count();
    }

    private function unreadCountQuery(int $viewerUserId): \Illuminate\Database\Query\Builder
    {
        return DB::table('messages')
            ->join('conversation_members', function ($join) use ($viewerUserId): void {
                $join->on('conversation_members.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_members.user_id', '=', $viewerUserId)
                    ->where('conversation_members.status', '=', 'active');
            })
            ->where('messages.sender_user_id', '!=', $viewerUserId)
            ->where('messages.is_deleted', '=', false)
            ->whereRaw('messages.id > coalesce(conversation_members.last_read_message_id, 0)');
    }

    private function resolveIsOnline(?User $user): bool
    {
        if (! $user || ! $user->is_online || $user->last_seen_at === null) {
            return false;
        }

        return $user->last_seen_at->greaterThanOrEqualTo(now()->subMinutes(self::PRESENCE_TTL_MINUTES));
    }

    private function findDirectConversation(int $viewerUserId, int $targetUserId): ?Conversation
    {
        return Conversation::query()
            ->where('type', 'direct')
            ->whereExists(function ($query) use ($viewerUserId) {
                $query->selectRaw('1')
                    ->from('conversation_members as cm1')
                    ->whereColumn('cm1.conversation_id', 'conversations.id')
                    ->where('cm1.user_id', $viewerUserId);
            })
            ->whereExists(function ($query) use ($targetUserId) {
                $query->selectRaw('1')
                    ->from('conversation_members as cm2')
                    ->whereColumn('cm2.conversation_id', 'conversations.id')
                    ->where('cm2.user_id', $targetUserId);
            })
            ->whereRaw('(select count(*) from conversation_members where conversation_members.conversation_id = conversations.id) = 2')
            ->first();
    }

    /**
     * @param  array{is_blocked_by_me: bool, is_blocked_by_other: bool}|null  $blockState
     *                                                                                     Pass a preloaded state when rendering a list; omit it for a single
     *                                                                                     conversation and it is looked up here.
     */
    private function conversationPayload(int $viewerUserId, Conversation $conversation, int $unreadCount = 0, ?array $blockState = null): array
    {
        $otherMember = $this->otherMember($conversation, $viewerUserId);

        $otherUser = $otherMember?->user;

        $blockState ??= $otherMember
            ? $this->blockStateFor($viewerUserId, (int) $otherMember->user_id)
            : ['is_blocked_by_me' => false, 'is_blocked_by_other' => false];

        return [
            'conversation_id' => $conversation->id,
            'is_messaging_stopped' => $conversation->messaging_stopped_at !== null,
            'messaging_stopped_at' => optional($conversation->messaging_stopped_at)?->toIso8601String(),
            'messaging_stopped_by_user_id' => $conversation->messaging_stopped_by_user_id,
            'is_blocked_by_me' => $blockState['is_blocked_by_me'],
            'is_blocked_by_other' => $blockState['is_blocked_by_other'],
            'is_blocked' => $blockState['is_blocked_by_me'] || $blockState['is_blocked_by_other'],
            'last_message' => $conversation->lastMessage?->body,
            'last_message_time' => optional($conversation->last_message_at)?->toIso8601String(),
            'unread_count' => $unreadCount,
            'user' => [
                'id' => $otherUser?->id,
                'username' => $otherUser?->username,
                'name' => $otherUser?->profile?->display_name ?? $otherUser?->full_name,
                'image' => stylebite_asset_url($otherUser?->avatar_url),
                'is_online' => $this->resolveIsOnline($otherUser),
                'last_seen_at' => optional($otherUser?->last_seen_at)?->toIso8601String(),
            ],
        ];
    }

    private function messagePayload(int $viewerUserId, Message $message, ?int $otherLastReadMessageId = null): array
    {
        $isMine = (int) $message->sender_user_id === (int) $viewerUserId;

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->profile?->display_name ?? $message->sender?->full_name,
            'body' => $message->body,
            'message_type' => $message->message_type,
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
            'delivered_at' => optional($message->delivered_at)?->toIso8601String(),
            'status' => $this->messageStatus($message, $isMine, $otherLastReadMessageId),
            'is_mine' => $isMine,
        ];
    }

    /**
     * In a direct chat the other member's read pointer is enough to decide "seen"
     * for every message, so per-message read lookups are never needed here.
     */
    private function messageStatus(Message $message, bool $isMine, ?int $otherLastReadMessageId): string
    {
        if (! $isMine) {
            return 'received';
        }

        if ($otherLastReadMessageId !== null && $message->id <= $otherLastReadMessageId) {
            return 'seen';
        }

        return $message->delivered_at !== null ? 'delivered' : 'sent';
    }

    private function setMessagingState(Request $request, int $conversationId, bool $shouldStop): JsonResponse
    {
        $viewer = $request->user();

        $conversation = Conversation::query()
            ->with('members')
            ->where('id', $conversationId)
            ->where('type', 'direct')
            ->firstOrFail();

        $isMember = $conversation->members->contains(fn (ConversationMember $member) => (int) $member->user_id === (int) $viewer->id && $member->status === 'active');

        if (! $isMember) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not allowed to update this chat.',
            ], Response::HTTP_FORBIDDEN);
        }

        $alreadyStopped = $conversation->messaging_stopped_at !== null;

        if ($shouldStop && $alreadyStopped) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Messaging is already stopped for this chat.',
                'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage']), $this->unreadCountFor($viewer->id, $conversation->id)),
            ]);
        }

        if (! $shouldStop && ! $alreadyStopped) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Messaging is already active for this chat.',
                'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage']), $this->unreadCountFor($viewer->id, $conversation->id)),
            ]);
        }

        $conversation->forceFill([
            'messaging_stopped_by_user_id' => $shouldStop ? $viewer->id : null,
            'messaging_stopped_at' => $shouldStop ? now() : null,
        ])->save();

        stylebite_broadcast(new MessagingStateChanged(
            conversationId: (int) $conversation->id,
            isMessagingStopped: $shouldStop,
            stoppedByUserId: $shouldStop ? (int) $viewer->id : null,
            stoppedAt: $shouldStop ? optional($conversation->messaging_stopped_at)?->toIso8601String() : null,
        ));

        return response()->json([
            'status_code' => 1,
            'message' => $shouldStop ? 'Messaging stopped successfully.' : 'Messaging resumed successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage']), $this->unreadCountFor($viewer->id, $conversation->id)),
        ]);
    }
}
