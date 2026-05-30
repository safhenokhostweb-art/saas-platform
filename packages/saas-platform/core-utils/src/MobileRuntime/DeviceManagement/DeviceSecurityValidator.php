<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement;

use Illuminate\Support\Facades\Cache;

class DeviceSecurityValidator
{
    private const MIN_OS = ['ios' => '15.0', 'android' => '10'];
    private const MIN_APP_VERSION = '1.0.0';

    public function validate(string $deviceId, array $deviceInfo): array
    {
        $issues = [];
        $score  = 100;

        if ($this->isJailbroken($deviceInfo)) {
            $issues[] = 'Device appears to be jailbroken/rooted';
            $score   -= 40;
        }

        if (! $this->isOsSupported($deviceInfo['platform'] ?? 'android', $deviceInfo['os_version'] ?? '')) {
            $issues[] = "OS version {$deviceInfo['os_version']} is below minimum supported";
            $score   -= 20;
        }

        if (! $this->isAppVersionSecure($deviceInfo['app_version'] ?? '')) {
            $issues[] = "App version {$deviceInfo['app_version']} is below minimum secure version";
            $score   -= 20;
        }

        return [
            'valid'  => empty($issues),
            'score'  => max(0, $score),
            'issues' => $issues,
        ];
    }

    public function isJailbroken(array $deviceInfo): bool
    {
        // Check for jailbreak indicators in device metadata
        $flags = $deviceInfo['security_flags'] ?? [];
        return in_array('jailbroken', $flags, true) || in_array('rooted', $flags, true);
    }

    public function isOsSupported(string $platform, string $osVersion): bool
    {
        $min = self::MIN_OS[strtolower($platform)] ?? '10';
        return version_compare($osVersion, $min, '>=');
    }

    public function isAppVersionSecure(string $appVersion): bool
    {
        return version_compare($appVersion, self::MIN_APP_VERSION, '>=');
    }

    public function checkCertificatePinning(array $headers): bool
    {
        // Validate presence of certificate pinning header set by mobile app
        return isset($headers['X-Mobile-Cert-Pin']);
    }

    public function generateSecurityReport(string $deviceId): array
    {
        $cached = Cache::get("mobile:devices:{$deviceId}");
        if (! $cached) {
            return ['device_id' => $deviceId, 'status' => 'unknown', 'score' => 0];
        }
        $result = $this->validate($deviceId, $cached);
        return array_merge($result, ['device_id' => $deviceId, 'generated_at' => now()->toIso8601String()]);
    }

    public function getMinSupportedOs(string $platform): string
    {
        return self::MIN_OS[strtolower($platform)] ?? '10';
    }

    public function getSecurityScore(string $deviceId): int
    {
        $cached = Cache::get("mobile:devices:{$deviceId}");
        if (! $cached) return 0;
        return $this->validate($deviceId, $cached)['score'];
    }
}
