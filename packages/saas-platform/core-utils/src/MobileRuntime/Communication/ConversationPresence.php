<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Carbon\Carbon;

class ConversationPresence
{
    public const STATUS_TYPING  = 'typing';
    public const STATUS_VIEWING = 'viewing';
    public const STATUS_IDLE    = 'idle';

    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly string $status = self::STATUS_VIEWING,
        public readonly ?string $deviceId = null,
        public readonly ?Carbon $since = null,
    ) {}

    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'tenant_id'       => $this->tenantId,
            'status'          => $this->status,
            'device_id'       => $this->deviceId,
            'since'           => $this->since?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            conversationId: $data['conversation_id'],
            userId:         $data['user_id'],
            tenantId:       $data['tenant_id'],
            status:         $data['status'] ?? self::STATUS_VIEWING,
            deviceId:       $data['device_id'] ?? null,
            since:          isset($data['since']) ? Carbon::parse($data['since']) : now(),
        );
    }

    public function isStale(int $ttlSeconds = 30): bool
    {
        $since = $this->since ?? now();
        return $since->diffInSeconds(now()) > $ttlSeconds;
    }

    public function withStatus(string $status): static
    {
        return new static(
            $this->conversationId,
            $this->userId,
            $this->tenantId,
            $status,
            $this->deviceId,
            $this->since,
        );
    }
}
