<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BroadcastingAuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'socket_id' => ['required', 'string'],
            'channel_name' => ['required', 'string'],
        ]);

        $key = (string) config('broadcasting.connections.pusher.key');
        $secret = (string) config('broadcasting.connections.pusher.secret');

        if ($key === '' || $secret === '') {
            return response()->json([
                'status_code' => 0,
                'message' => 'Realtime is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $user = $request->user();
        $channel = $validated['channel_name'];
        $socketId = $validated['socket_id'];

        if (str_starts_with($channel, 'private-user.')) {
            $channelUserId = (int) substr($channel, strlen('private-user.'));

            if ($channelUserId !== (int) $user->id) {
                return $this->forbidden();
            }

            return response()->json([
                'status_code' => 1,
                'auth' => $this->sign($key, $secret, $socketId, $channel),
            ]);
        }

        if (str_starts_with($channel, 'presence-conversation.')) {
            $conversationId = (int) substr($channel, strlen('presence-conversation.'));
            $conversation = Conversation::query()->find($conversationId);

            $isMember = $conversation && ConversationMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $isMember || $conversation->request_status === 'rejected') {
                return $this->forbidden();
            }

            $channelData = json_encode([
                'user_id' => (string) $user->id,
                'user_info' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ], JSON_THROW_ON_ERROR);

            return response()->json([
                'status_code' => 1,
                'auth' => $this->sign($key, $secret, $socketId, $channel, $channelData),
                'channel_data' => $channelData,
            ]);
        }

        return $this->forbidden();
    }

    private function sign(string $key, string $secret, string $socketId, string $channel, ?string $channelData = null): string
    {
        $stringToSign = $socketId.':'.$channel;

        if ($channelData !== null) {
            $stringToSign .= ':'.$channelData;
        }

        return $key.':'.hash_hmac('sha256', $stringToSign, $secret);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => 'You are not allowed to subscribe to this channel.',
        ], Response::HTTP_FORBIDDEN);
    }
}
