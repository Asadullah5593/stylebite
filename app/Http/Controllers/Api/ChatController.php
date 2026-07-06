<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
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

        return response()->json([
            'status_code' => 1,
            'message' => 'Chats fetched successfully.',
            'chats' => $chats->map(fn (Conversation $conversation) => $this->conversationPayload($viewer->id, $conversation)),
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

        return response()->json([
            'status_code' => 1,
            'message' => 'Messages fetched successfully.',
            'chat' => $this->conversationPayload($viewer->id, $conversation->fresh(['members.user.profile', 'lastMessage'])),
            'messages' => $messages->map(fn (Message $message) => $this->messagePayload($viewer->id, $message)),
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

        $totalMessages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_deleted', false)
            ->count();

        if ($totalMessages > 0) {
            $distinctSenders = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('is_deleted', false)
                ->distinct()
                ->pluck('sender_user_id');

            if ($distinctSenders->count() === 1 && (int) $distinctSenders->first() === (int) $viewer->id) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Wait for reply before sending another message.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
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

        $recipient = $conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id !== (int) $viewer->id)?->user;

        if ($recipient) {
            stylebite_notify_user(
                $recipient->id,
                $viewer->id,
                'message',
                'conversation',
                $conversation->id,
                $viewer->full_name ?: $viewer->username,
                (string) $message->body,
                '/chat/'.$conversation->id,
                $viewer->avatar_url
            );
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Message sent successfully.',
            'message_data' => $this->messagePayload($viewer->id, $message->fresh('sender.profile')),
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
                'is_online' => (bool) $user->is_online,
                'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
            ],
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

    private function conversationPayload(int $viewerUserId, Conversation $conversation): array
    {
        $otherMember = $conversation->members
            ->first(fn (ConversationMember $member) => (int) $member->user_id !== (int) $viewerUserId);

        $otherUser = $otherMember?->user;

        return [
            'conversation_id' => $conversation->id,
            'is_messaging_stopped' => $conversation->messaging_stopped_at !== null,
            'messaging_stopped_at' => optional($conversation->messaging_stopped_at)?->toIso8601String(),
            'last_message' => $conversation->lastMessage?->body,
            'last_message_time' => optional($conversation->last_message_at)?->toIso8601String(),
            'user' => [
                'id' => $otherUser?->id,
                'username' => $otherUser?->username,
                'name' => $otherUser?->profile?->display_name ?? $otherUser?->full_name,
                'image' => stylebite_asset_url($otherUser?->avatar_url),
                'is_online' => (bool) ($otherUser?->is_online ?? false),
            ],
        ];
    }

    private function messagePayload(int $viewerUserId, Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->profile?->display_name ?? $message->sender?->full_name,
            'body' => $message->body,
            'message_type' => $message->message_type,
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
            'is_mine' => (int) $message->sender_user_id === (int) $viewerUserId,
        ];
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
}
