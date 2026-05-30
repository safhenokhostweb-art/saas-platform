<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\ClientRuntime\Enums\ClientPlatform;
use SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts\NotificationProviderInterface;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobilePushTokenRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\Notifications\PushDeliveryRuntime;

class FirebasePushProvider implements NotificationProviderInterface
{
    private const PRIORITY = 2;

    public function __construct(
        private readonly PushDeliveryRuntime       $pushRuntime,
        private readonly MobilePushTokenRegistry   $tokenRegistry,
    ) {}

    public function getName(): string
    {
        return 'firebase_push';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function isAvailable(): bool
    {
        // Firebase is optional — system must function without it
        $serverKey = Config::get('mobile.fcm_server_key', '');
        return ! empty($serverKey);
    }

    public function canDeliver(string $platform): bool
    {
        try {
            $clientPlatform = ClientPlatform::fromString($platform);
            return $clientPlatform->supportsFirebasePush();
        } catch (\Throwable) {
            return false;
        }
    }

    public function deliver(string $deviceId, array $notification): bool
    {
        if (! $this->isAvailable()) {
            Log::debug('client.transport.firebase.unavailable', ['device_id' => $deviceId]);
            return false;
        }

        return $this->pushRuntime->deliver($deviceId, $notification);
    }

    public function getHealthStatus(): array
    {
        $available = $this->isAvailable();
        return [
            'provider'   => $this->getName(),
            'priority'   => $this->getPriority(),
            'available'  => $available,
            'transport'  => 'fcm',
            'configured' => ! empty(Config::get('mobile.fcm_server_key', '')),
            'optional'   => true,
        ];
    }
}
