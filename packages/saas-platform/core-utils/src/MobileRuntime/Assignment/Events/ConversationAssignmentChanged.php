<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Assignment\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAssignmentChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $tenantId,
        public readonly string $event,
        public readonly array  $metadata,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversationId}"),
            new PrivateChannel("tenant.{$this->tenantId}.inbox"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.assignment.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'tenant_id'       => $this->tenantId,
            'event'           => $this->event,
            'assigned_to'     => $this->metadata['assigned_to'] ?? null,
            'escalated'       => $this->metadata['escalated']   ?? false,
            'timestamp'       => now()->toIso8601String(),
        ];
    }
}
