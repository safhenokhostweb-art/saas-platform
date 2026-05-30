<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement;

use Illuminate\Support\Facades\Cache;

class DeviceCapabilityResolver
{
    private const PREFIX = 'mobile:device_caps:';
    private const TTL    = 3600;

    private array $capabilityMatrix = [
        'push_notifications' => ['min_ios' => '12.0', 'min_android' => '8',  'min_app' => '1.0.0'],
        'biometric_auth'     => ['min_ios' => '13.0', 'min_android' => '9',  'min_app' => '1.0.0'],
        'background_sync'    => ['min_ios' => '13.0', 'min_android' => '8',  'min_app' => '1.0.0'],
        'camera'             => ['min_ios' => '12.0', 'min_android' => '8',  'min_app' => '1.0.0'],
        'file_upload'        => ['min_ios' => '12.0', 'min_android' => '8',  'min_app' => '1.0.0'],
        'voice_messages'     => ['min_ios' => '14.0', 'min_android' => '10', 'min_app' => '1.5.0'],
        'video_calls'        => ['min_ios' => '15.0', 'min_android' => '11', 'min_app' => '2.0.0'],
        'offline_mode'       => ['min_ios' => '13.0', 'min_android' => '9',  'min_app' => '1.2.0'],
    ];

    public function resolve(string $deviceId): array
    {
        return Cache::remember(self::PREFIX . $deviceId, self::TTL, function () use ($deviceId) {
            $device = Cache::get("mobile:devices:{$deviceId}");
            if (! $device) return [];
            return $this->getDeviceCapabilities(
                $device['platform'] ?? 'android',
                $device['os_version'] ?? '10',
                $device['app_version'] ?? '1.0.0'
            );
        });
    }

    public function supports(string $deviceId, string $capability): bool
    {
        return in_array($capability, array_keys(array_filter($this->resolve($deviceId))), true);
    }

    public function getDeviceCapabilities(string $platform, string $osVersion, string $appVersion): array
    {
        $result   = [];
        $platform = strtolower($platform);
        foreach ($this->capabilityMatrix as $cap => $requirements) {
            $minOs  = $requirements["min_{$platform}"] ?? $requirements['min_android'];
            $minApp = $requirements['min_app'];
            $result[$cap] = version_compare($osVersion, $minOs, '>=')
                && version_compare($appVersion, $minApp, '>=');
        }
        return $result;
    }

    public function updateCapabilities(string $deviceId, array $capabilities): void
    {
        Cache::put(self::PREFIX . $deviceId, $capabilities, self::TTL);
    }

    public function getSupportedCapabilities(): array
    {
        return array_keys($this->capabilityMatrix);
    }
}
