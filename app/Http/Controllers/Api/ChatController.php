<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageRead;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\ChatRealtime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public const REQUEST_MESSAGE_LIMIT = 3;

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

        $blockResponse = $this->blockResponse((int) $viewer->id, (int) $target->id);
        if ($blockResponse) {
            return $blockResponse;
        }

        $conversation = DB::transaction(function () use ($viewer, $target): Conversation {
            $conversation = $this->findDirectConversation($viewer->id, $target->id);

            if ($conversation) {
                if ($conversation->request_status === 'rejected') {
                    $conversation->forceFill([
                        'request_status' => 'pending',
                        'requested_by_user_id' => $viewer->id,
                    ])->save();
                }

                return $conversation;
            }

            $conversation = Conversation::query()->create([
                'type' => 'direct',
                'created_by_user_id' => $viewer->id,
                'request_status' => 'pending',
                'requested_by_user_id' => $viewer->id,
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
            'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
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
            ->where(function (Builder $query): void {
                $query->whereNull('request_status')
                    ->orWhereIn('request_status', ['pending', 'accepted']);
            })
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
        $totalUnreadCount = $this->totalUnreadCount((int) $viewer->id);

        $chats = $baseQuery
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->skip($skip)
            ->take($perPage)
            ->get();

        $unreadByConversation = $this->unreadCountsFor((int) $viewer->id, $chats->pluck('id')->all());
        $blockFlags = $this->blockFlagsFor((int) $viewer->id, $chats);

        return response()->json([
            'status_code' => 1,
            'message' => 'Chats fetched successfully.',
            'total_unread_count' => $totalUnreadCount,
            'chats' => $chats->map(fn (Conversation $conversation) => $this->conversationPayload(
                $viewer->id,
                $conversation,
                (int) ($unreadByConversation[$conversation->id] ?? 0),
                $blockFlags[$conversation->id] ?? ['me' => false, 'other' => false],
            )),
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

        $blockResponse = $this->blockResponse((int) $viewer->id, (int) $target->id);
        if ($blockResponse) {
            return $blockResponse;
        }

        $conversation = $this->findDirectConversation($viewer->id, $target->id);

        if (! $conversation || $conversation->request_status === 'rejected') {
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

        $conversation->load(['members.user.profile', 'lastMessage']);

        $perPage = 10;
        $skip = (int) ($validated['skip'] ?? $validated['offset'] ?? 0);
        $page = (int) ($validated['page'] ?? (intdiv($skip, $perPage) + 1));

        $baseQuery = Message::query()
            ->with(['sender.profile', 'reactions.user'])
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

        if ($skip === 0) {
            $latestId = (int) ($messages->max('id') ?? 0);
            if ($latestId > 0) {
                $this->rememberRead($conversation, (int) $viewer->id, $latestId);
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Messages fetched successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation),
            'messages' => $messages->map(fn (Message $message) => $this->messagePayload($viewer->id, $message, $conversation)),
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
        $conversation = $this->memberConversationOrFail($viewer->id, $conversationId);

        $otherUserId = $this->otherUserId($conversation, $viewer->id);
        $blockResponse = $this->blockResponse((int) $viewer->id, $otherUserId);
        if ($blockResponse) {
            return $blockResponse;
        }

        if ($conversation->request_status === 'rejected') {
            return response()->json([
                'status_code' => 0,
                'message' => 'This chat request was rejected.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($conversation->messaging_stopped_at !== null) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Messaging has been stopped for this chat.',
                'is_messaging_stopped' => true,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $isPending = $conversation->request_status === 'pending';
        $isRequester = (int) $conversation->requested_by_user_id === (int) $viewer->id;

        if ($isPending && $isRequester) {
            $outboundCount = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_user_id', $viewer->id)
                ->where('is_deleted', false)
                ->count();

            if ($outboundCount >= self::REQUEST_MESSAGE_LIMIT) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Wait for them to accept this chat request before sending more messages.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $message = DB::transaction(function () use ($conversation, $viewer, $validated, $isPending, $isRequester): Message {
            if ($isPending && ! $isRequester) {
                $conversation->forceFill([
                    'request_status' => 'accepted',
                ])->save();
            }

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

        $recipient = $conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id !== (int) $viewer->id)?->user;

        if ($recipient) {
            [$notifyBody, $notifyImage] = $this->notificationPreview(
                (string) $message->body,
                $viewer->avatar_url
            );
            stylebite_notify_user(
                $recipient->id,
                $viewer->id,
                'message',
                'message',
                $conversation->id,
                $viewer->full_name ?: $viewer->username,
                $notifyBody,
                '/chat/'.$conversation->id,
                $notifyImage
            );
        }

        $conversation->refresh()->load(['members.user.profile', 'lastMessage']);

        return response()->json([
            'status_code' => 1,
            'message' => 'Message sent successfully.',
            'message_data' => $this->messagePayload(
                $viewer->id,
                $message->fresh(['sender.profile', 'reactions.user']),
                $conversation
            ),
            'chat' => $this->conversationPayload($viewer->id, $conversation),
        ]);
    }

    public function toggleReaction(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

        $emoji = trim($validated['emoji']);
        if ($emoji === '') {
            return response()->json([
                'status_code' => 0,
                'message' => 'Emoji is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $viewer = $request->user();
        $conversation = $this->memberConversationOrFail($viewer->id, $conversationId);
        $conversation->load(['members.user.profile', 'lastMessage']);

        $message = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->whereKey($messageId)
            ->firstOrFail();

        $existing = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $viewer->id)
            ->first();

        if ($existing && $existing->emoji === $emoji) {
            $existing->delete();
        } elseif ($existing) {
            $existing->forceFill(['emoji' => $emoji])->save();
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $viewer->id,
                'emoji' => $emoji,
            ]);
        }

        $message->load(['sender.profile', 'reactions.user']);

        return response()->json([
            'status_code' => 1,
            'message' => 'Reaction updated.',
            'message_data' => $this->messagePayload($viewer->id, $message, $conversation),
        ]);
    }

    public function markRead(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'up_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $viewer = $request->user();
        $conversation = $this->memberConversationOrFail($viewer->id, $conversationId);

        $upToMessageId = (int) ($validated['up_to_message_id'] ?? 0);
        if ($upToMessageId <= 0) {
            $upToMessageId = (int) (Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('is_deleted', false)
                ->max('id') ?? 0);
        }

        if ($upToMessageId <= 0) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Chat marked as read.',
                'unread_count' => 0,
            ]);
        }

        $this->rememberRead($conversation, (int) $viewer->id, $upToMessageId);

        return response()->json([
            'status_code' => 1,
            'message' => 'Chat marked as read.',
            'unread_count' => 0,
            'last_read_message_id' => $upToMessageId,
        ]);
    }

    public function sync(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'after_message_id' => ['required', 'integer', 'min:0'],
        ]);

        $viewer = $request->user();
        $conversation = $this->memberConversationOrFail($viewer->id, $conversationId);
        $conversation->load(['members.user.profile', 'lastMessage']);

        $messages = Message::query()
            ->with(['sender.profile', 'reactions.user'])
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->where('id', '>', (int) $validated['after_message_id'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Messages synced successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation),
            'messages' => $messages->map(fn (Message $message) => $this->messagePayload($viewer->id, $message, $conversation)),
        ]);
    }

    public function acceptRequest(Request $request, int $conversationId): JsonResponse
    {
        return $this->setRequestStatus($request, $conversationId, 'accepted');
    }

    public function rejectRequest(Request $request, int $conversationId): JsonResponse
    {
        return $this->setRequestStatus($request, $conversationId, 'rejected');
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
                'is_online' => (bool) $user->is_online,
                'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
            ],
        ]);
    }

    private function setRequestStatus(Request $request, int $conversationId, string $status): JsonResponse
    {
        $viewer = $request->user();
        $conversation = $this->memberConversationOrFail($viewer->id, $conversationId);

        $isIncoming = $conversation->request_status === 'pending'
            && (int) $conversation->requested_by_user_id !== (int) $viewer->id;

        if ($conversation->request_status === $status) {
            return response()->json([
                'status_code' => 1,
                'message' => $status === 'accepted'
                    ? 'Chat request already accepted.'
                    : 'Chat request already rejected.',
                'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
            ]);
        }

        if (! $isIncoming && $conversation->request_status === 'pending') {
            return response()->json([
                'status_code' => 0,
                'message' => 'Only the recipient can respond to this chat request.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($conversation->request_status !== 'pending') {
            return response()->json([
                'status_code' => 0,
                'message' => 'This chat request cannot be updated.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $conversation->forceFill([
            'request_status' => $status,
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => $status === 'accepted'
                ? 'Chat request accepted.'
                : 'Chat request rejected.',
            'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
        ]);
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

    private function memberConversationOrFail(int $viewerUserId, int $conversationId): Conversation
    {
        $conversation = Conversation::query()
            ->with(['members.user.profile', 'lastMessage'])
            ->where('id', $conversationId)
            ->where('type', 'direct')
            ->firstOrFail();

        $isMember = $conversation->members->contains(
            fn (ConversationMember $member) => (int) $member->user_id === (int) $viewerUserId && $member->status === 'active'
        );

        if (! $isMember) {
            abort(response()->json([
                'status_code' => 0,
                'message' => 'You are not allowed to access this chat.',
            ], Response::HTTP_FORBIDDEN));
        }

        return $conversation;
    }

    private function conversationPayload(
        int $viewerUserId,
        Conversation $conversation,
        ?int $unreadCount = null,
        ?array $blockFlags = null,
    ): array {
        $otherMember = $conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id !== (int) $viewerUserId);

        $otherUser = $otherMember?->user;
        $unreadCount ??= $this->unreadCountFor($viewerUserId, $conversation);
        $blockFlags ??= $this->blockPair($viewerUserId, (int) ($otherUser?->id ?? 0));

        $requestStatus = $conversation->request_status;
        $isPending = $requestStatus === 'pending';
        $isRequester = (int) $conversation->requested_by_user_id === (int) $viewerUserId;
        $isAccepted = $requestStatus === null || $requestStatus === 'accepted';

        return [
            'conversation_id' => $conversation->id,
            'id' => $conversation->id,
            'is_messaging_stopped' => $conversation->messaging_stopped_at !== null,
            'messaging_stopped_at' => optional($conversation->messaging_stopped_at)?->toIso8601String(),
            'last_message' => $conversation->lastMessage?->body,
            'last_message_time' => optional($conversation->last_message_at)?->toIso8601String(),
            'unread_count' => $unreadCount,
            'is_blocked_by_me' => (bool) $blockFlags['me'],
            'is_blocked_by_other' => (bool) $blockFlags['other'],
            'request_status' => $isAccepted && $requestStatus === null ? 'accepted' : $requestStatus,
            'chat_request_status' => $isAccepted && $requestStatus === null ? 'accepted' : $requestStatus,
            'is_pending_request' => $isPending,
            'is_incoming_request' => $isPending && ! $isRequester,
            'is_accepted' => $isAccepted,
            'initiated_by_me' => $isRequester,
            'is_requester' => $isRequester,
            'requested_by_user_id' => $conversation->requested_by_user_id,
            'user' => [
                'id' => $otherUser?->id,
                'username' => $otherUser?->username,
                'name' => $otherUser?->profile?->display_name ?? $otherUser?->full_name,
                'image' => stylebite_asset_url($otherUser?->avatar_url),
                'is_online' => (bool) ($otherUser?->is_online ?? false),
            ],
        ];
    }

    private function messagePayload(int $viewerUserId, Message $message, ?Conversation $conversation = null): array
    {
        $isMine = (int) $message->sender_user_id === (int) $viewerUserId;
        $otherLastRead = 0;

        if ($conversation) {
            $otherLastRead = (int) ($conversation->members
                ->first(fn (ConversationMember $member) => (int) $member->user_id !== (int) $viewerUserId)
                ?->last_read_message_id ?? 0);
        }

        $isSeen = $isMine && $otherLastRead >= (int) $message->id;
        $status = 'sent';
        if ($isSeen) {
            $status = 'seen';
        } elseif ($message->delivered_at !== null) {
            $status = 'delivered';
        }

        $message->loadMissing('reactions.user');
        $reactionGroups = [];
        $myReaction = null;
        foreach ($message->reactions as $reaction) {
            $emoji = (string) $reaction->emoji;
            if ($emoji === '') {
                continue;
            }
            if (! isset($reactionGroups[$emoji])) {
                $reactionGroups[$emoji] = [
                    'emoji' => $emoji,
                    'count' => 0,
                    'user_ids' => [],
                ];
            }
            $reactionGroups[$emoji]['count']++;
            $reactionGroups[$emoji]['user_ids'][] = (int) $reaction->user_id;
            if ((int) $reaction->user_id === (int) $viewerUserId) {
                $myReaction = $emoji;
            }
        }

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->profile?->display_name ?? $message->sender?->full_name,
            'body' => $message->body,
            'message_type' => $message->message_type,
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
            'created_at' => optional($message->sent_at ?? $message->created_at)?->toIso8601String(),
            'is_mine' => $isMine,
            'status' => $status,
            'delivery_status' => $status,
            'is_seen' => $isSeen,
            'delivered_at' => optional($message->delivered_at)?->toIso8601String(),
            'my_reaction' => $myReaction,
            'reactions' => array_values($reactionGroups),
            'sender' => [
                'id' => $message->sender?->id,
                'username' => $message->sender?->username,
                'name' => $message->sender?->profile?->display_name ?? $message->sender?->full_name,
            ],
        ];
    }

    private function setMessagingState(Request $request, int $conversationId, bool $shouldStop): JsonResponse
    {
        $viewer = $request->user();
        $conversation = $this->memberConversationOrFail($viewer->id, $conversationId);

        $alreadyStopped = $conversation->messaging_stopped_at !== null;

        if ($shouldStop && $alreadyStopped) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Messaging is already stopped for this chat.',
                'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
            ]);
        }

        if (! $shouldStop && ! $alreadyStopped) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Messaging is already active for this chat.',
                'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
            ]);
        }

        $conversation->forceFill([
            'messaging_stopped_by_user_id' => $shouldStop ? $viewer->id : null,
            'messaging_stopped_at' => $shouldStop ? now() : null,
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => $shouldStop ? 'Messaging stopped successfully.' : 'Messaging resumed successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
        ]);
    }

    private function unreadCountFor(int $viewerUserId, Conversation $conversation): int
    {
        $lastRead = (int) ($conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id === (int) $viewerUserId)
            ?->last_read_message_id ?? 0);

        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->where('sender_user_id', '!=', $viewerUserId)
            ->where('id', '>', $lastRead)
            ->count();
    }

    /**
     * @param  array<int, int|string>  $conversationIds
     * @return array<int, int>
     */
    private function unreadCountsFor(int $viewerUserId, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return Message::query()
            ->selectRaw('messages.conversation_id, count(*) as unread_count')
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.is_deleted', false)
            ->where('messages.sender_user_id', '!=', $viewerUserId)
            ->whereRaw(
                'messages.id > COALESCE((select last_read_message_id from conversation_members where conversation_members.conversation_id = messages.conversation_id and conversation_members.user_id = ?), 0)',
                [$viewerUserId]
            )
            ->groupBy('messages.conversation_id')
            ->pluck('unread_count', 'conversation_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function totalUnreadCount(int $viewerUserId): int
    {
        return (int) Message::query()
            ->where('is_deleted', false)
            ->where('sender_user_id', '!=', $viewerUserId)
            ->whereIn('conversation_id', function ($query) use ($viewerUserId): void {
                $query->select('conversations.id')
                    ->from('conversations')
                    ->join('conversation_members', 'conversation_members.conversation_id', '=', 'conversations.id')
                    ->where('conversation_members.user_id', $viewerUserId)
                    ->where('conversation_members.status', 'active')
                    ->where('conversations.type', 'direct')
                    ->where(function ($statusQuery): void {
                        $statusQuery->whereNull('conversations.request_status')
                            ->orWhereIn('conversations.request_status', ['pending', 'accepted']);
                    });
            })
            ->whereRaw(
                'messages.id > COALESCE((select last_read_message_id from conversation_members where conversation_members.conversation_id = messages.conversation_id and conversation_members.user_id = ?), 0)',
                [$viewerUserId]
            )
            ->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Conversation>  $chats
     * @return array<int, array{me: bool, other: bool}>
     */
    private function blockFlagsFor(int $viewerUserId, $chats): array
    {
        $otherIds = $chats
            ->map(fn (Conversation $conversation) => $this->otherUserId($conversation, $viewerUserId))
            ->filter()
            ->unique()
            ->values();

        if ($otherIds->isEmpty()) {
            return [];
        }

        $blockedByMe = UserBlock::query()
            ->where('blocker_user_id', $viewerUserId)
            ->whereIn('blocked_user_id', $otherIds)
            ->pluck('blocked_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $blockedByOther = UserBlock::query()
            ->where('blocked_user_id', $viewerUserId)
            ->whereIn('blocker_user_id', $otherIds)
            ->pluck('blocker_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $flags = [];
        foreach ($chats as $conversation) {
            $otherId = $this->otherUserId($conversation, $viewerUserId);
            $flags[$conversation->id] = [
                'me' => in_array($otherId, $blockedByMe, true),
                'other' => in_array($otherId, $blockedByOther, true),
            ];
        }

        return $flags;
    }

    private function rememberRead(Conversation $conversation, int $viewerUserId, int $upToMessageId): bool
    {
        if ($upToMessageId <= 0) {
            return false;
        }

        $previous = (int) (ConversationMember::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $viewerUserId)
            ->value('last_read_message_id') ?? 0);

        if ($upToMessageId <= $previous) {
            return false;
        }

        $now = now();
        $updated = ConversationMember::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $viewerUserId)
            ->where(function (Builder $query) use ($upToMessageId): void {
                $query->whereNull('last_read_message_id')
                    ->orWhere('last_read_message_id', '<', $upToMessageId);
            })
            ->update([
                'last_read_message_id' => $upToMessageId,
                'last_read_at' => $now,
            ]);

        if ($updated === 0) {
            return false;
        }

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->where('sender_user_id', '!=', $viewerUserId)
            ->where('id', '>', $previous)
            ->where('id', '<=', $upToMessageId)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => $now]);

        try {
            $messageIds = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('is_deleted', false)
                ->where('sender_user_id', '!=', $viewerUserId)
                ->where('id', '>', $previous)
                ->where('id', '<=', $upToMessageId)
                ->pluck('id');

            foreach ($messageIds->chunk(100) as $chunk) {
                MessageRead::query()->insertOrIgnore($chunk->map(fn ($messageId) => [
                    'message_id' => (int) $messageId,
                    'user_id' => $viewerUserId,
                    'read_at' => $now->toDateTimeString(),
                ])->all());
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->broadcastRead($conversation, $viewerUserId, $upToMessageId, $now->toIso8601String());

        return true;
    }

    private function broadcastRead(
        Conversation $conversation,
        int $readerUserId,
        int $lastReadMessageId,
        string $readAt,
    ): void {
        $conversation->unsetRelation('members');
        $conversation->load(['members.user.profile', 'lastMessage']);

        $updates = [];
        foreach ($conversation->members as $member) {
            $userId = (int) $member->user_id;
            if ($userId <= 0) {
                continue;
            }

            $updates[] = [
                'channel' => 'private-user.'.$userId,
                'payload' => [
                    'chat' => $this->conversationPayload($userId, $conversation),
                    'total_unread_count' => $this->totalUnreadCount($userId),
                ],
            ];
        }

        $conversationId = (int) $conversation->id;
        $readPayload = [
            'reader_user_id' => $readerUserId,
            'last_read_message_id' => $lastReadMessageId,
            'message_ids' => [],
            'read_at' => $readAt,
        ];

        app()->terminating(function () use ($conversationId, $readPayload, $updates): void {
            ChatRealtime::trigger(
                ['presence-conversation.'.$conversationId],
                'messages.read',
                $readPayload,
            );

            foreach ($updates as $update) {
                ChatRealtime::trigger(
                    [$update['channel']],
                    'chat.updated',
                    $update['payload'],
                );
            }
        });
    }

    private function otherUserId(Conversation $conversation, int $viewerUserId): int
    {
        return (int) ($conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id !== (int) $viewerUserId)
            ?->user_id ?? 0);
    }

    private function blockPair(int $viewerUserId, int $otherUserId): array
    {
        if ($otherUserId <= 0) {
            return ['me' => false, 'other' => false];
        }

        return [
            'me' => UserBlock::query()
                ->where('blocker_user_id', $viewerUserId)
                ->where('blocked_user_id', $otherUserId)
                ->exists(),
            'other' => UserBlock::query()
                ->where('blocker_user_id', $otherUserId)
                ->where('blocked_user_id', $viewerUserId)
                ->exists(),
        ];
    }

    private function blockResponse(int $viewerUserId, int $otherUserId): ?JsonResponse
    {
        $flags = $this->blockPair($viewerUserId, $otherUserId);

        if (! $flags['me'] && ! $flags['other']) {
            return null;
        }

        return response()->json([
            'status_code' => 0,
            'message' => $flags['me']
                ? 'You have blocked this user.'
                : 'This user has blocked you.',
            'is_blocked_by_me' => $flags['me'],
            'is_blocked_by_other' => $flags['other'],
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function notificationPreview(string $body, ?string $avatarUrl): array
    {
        $prefix = 'sbshare:v1:';
        if (! str_starts_with($body, $prefix)) {
            return [$body, $avatarUrl];
        }

        $decoded = json_decode(substr($body, strlen($prefix)), true);
        $kind = is_array($decoded) ? (string) ($decoded['kind'] ?? 'post') : 'post';
        $preview = is_array($decoded) ? trim((string) ($decoded['preview'] ?? '')) : '';

        return [
            $kind === 'reel' ? 'Shared a reel' : 'Shared a post',
            $preview !== '' ? $preview : $avatarUrl,
        ];
    }
}
