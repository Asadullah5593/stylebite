<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The recipient's device has the messages — the sender flips them to "delivered".
 */
class MessagesDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $messageIds
     */
    public function __construct(
        public int $conversationId,
        public int $recipientUserId,
        public array $messageIds,
        public string $deliveredAt,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('conversation.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'messages.delivered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'recipient_user_id' => $this->recipientUserId,
            'message_ids' => $this->messageIds,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}
