<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts;

interface NotificationProviderInterface
{
    public function getName(): string;

    public function getPriority(): int;

    public function isAvailable(): bool;

    public function canDeliver(string $platform): bool;

    public function deliver(string $deviceId, array $notification): bool;

    public function getHealthStatus(): array;
}
