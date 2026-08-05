<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to one specific user's own channel, because unread counts are per-user.
 * This is what keeps the chat list and the tab badge live while the user is
 * anywhere else in the app.
 */
class ChatListUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $chat
     */
    public function __construct(
        public int $userId,
        public array $chat,
        public int $totalUnreadCount,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'chat.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'chat' => $this->chat,
            'total_unread_count' => $this->totalUnreadCount,
        ];
    }
}
