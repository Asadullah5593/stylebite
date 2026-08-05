<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One side stopped or resumed messaging — the other side must lock/unlock its
 * composer immediately rather than discovering it on a failed send.
 */
class MessagingStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public bool $isMessagingStopped,
        public ?int $stoppedByUserId,
        public ?string $stoppedAt,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('conversation.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'messaging.state';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'is_messaging_stopped' => $this->isMessagingStopped,
            'messaging_stopped_by_user_id' => $this->stoppedByUserId,
            'messaging_stopped_at' => $this->stoppedAt,
        ];
    }
}
