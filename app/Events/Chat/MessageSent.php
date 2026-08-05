<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to everyone currently inside the thread.
 *
 * ShouldBroadcastNow (not ShouldBroadcast) is deliberate: the queue on this host
 * is drained by a once-per-minute cron, so a queued broadcast would arrive up to
 * 60 seconds late and defeat the point of realtime.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public int $conversationId,
        public array $message,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('conversation.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
        ];
    }
}
