<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

class DispatchResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $queue,
        public readonly ?int    $priority,
        public readonly ?float  $throttleFactor,
        public readonly ?string $reason
    ) {}

    public static function dispatched(string $queue, int $priority, float $throttleFactor = 1.0): self
    {
        return new self('dispatched', $queue, $priority, $throttleFactor, null);
    }

    public static function deferred(string $queue, int $priority): self
    {
        return new self('deferred', $queue, $priority, null, null);
    }

    public static function rejected(string $reason): self
    {
        return new self('rejected', null, null, null, $reason);
    }

    public function wasDispatched(): bool { return $this->status === 'dispatched'; }
    public function wasDeferred(): bool   { return $this->status === 'deferred'; }
    public function wasRejected(): bool   { return $this->status === 'rejected'; }

    public function toArray(): array
    {
        return [
            'status'         => $this->status,
            'queue'          => $this->queue,
            'priority'       => $this->priority,
            'throttle_factor' => $this->throttleFactor,
            'reason'         => $this->reason,
        ];
    }
}
