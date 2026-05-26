<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

class EnforcementResult
{
    private function __construct(
        public readonly bool    $allowed,
        public readonly ?float  $throttleFactor,
        public readonly ?string $reason,
        public readonly array   $affectedResources
    ) {}

    public static function allow(): self
    {
        return new self(true, null, null, []);
    }

    public static function throttled(float $factor, string $reason, array $resources = []): self
    {
        return new self(false, $factor, $reason, $resources);
    }

    public static function denied(string $reason, array $resources = []): self
    {
        return new self(false, 0.0, $reason, $resources);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isThrottled(): bool
    {
        return !$this->allowed && $this->throttleFactor !== null && $this->throttleFactor > 0;
    }

    public function isDenied(): bool
    {
        return !$this->allowed && $this->throttleFactor === 0.0;
    }

    public function toArray(): array
    {
        return [
            'allowed'           => $this->allowed,
            'throttle_factor'   => $this->throttleFactor,
            'reason'            => $this->reason,
            'affected_resources' => $this->affectedResources,
        ];
    }
}
