<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Anomaly;

use Illuminate\Support\Facades\Cache;

class SecurityAnomalyDetector
{
    private const AUTH_FAILURE_THRESHOLD  = 20;
    private const AUTH_FAILURE_WINDOW_MIN = 15;
    private const AUTH_FAILURE_CACHE_KEY  = 'telemetry.security.auth_failures';

    public function detect(): array
    {
        $anomalies = [];
        $anomalies = array_merge($anomalies, $this->checkAuthFailureBurst());
        return $anomalies;
    }

    public function recordAuthFailure(string $ip = ''): void
    {
        $window = (int) (time() / (self::AUTH_FAILURE_WINDOW_MIN * 60));
        $key    = self::AUTH_FAILURE_CACHE_KEY . '.' . $window;
        Cache::increment($key);
        Cache::put($key . '.expires', 1, self::AUTH_FAILURE_WINDOW_MIN * 60 * 2);
    }

    public function authFailureCountInWindow(): int
    {
        $window = (int) (time() / (self::AUTH_FAILURE_WINDOW_MIN * 60));
        return (int) Cache::get(self::AUTH_FAILURE_CACHE_KEY . '.' . $window, 0);
    }

    private function checkAuthFailureBurst(): array
    {
        $count = $this->authFailureCountInWindow();

        if ($count >= self::AUTH_FAILURE_THRESHOLD) {
            return [RuntimeAnomalyEngine::buildAnomaly(
                'SECURITY_AUTH_FAILURE_BURST',
                'critical',
                "Auth failure burst: {$count} failures in " . self::AUTH_FAILURE_WINDOW_MIN . " min window",
                [
                    'failure_count'   => $count,
                    'window_minutes'  => self::AUTH_FAILURE_WINDOW_MIN,
                    'threshold'       => self::AUTH_FAILURE_THRESHOLD,
                ]
            )];
        }

        return [];
    }
}
