<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle;

use Illuminate\Support\Facades\Cache;

class RequestRateRegulator
{
    private const RATE_KEY_PREFIX = 'governance.rate.';
    private const WINDOW_SECONDS  = 60;

    public function recordRequest(string $scope, int $count = 1): void
    {
        $key = self::RATE_KEY_PREFIX . $scope . '.' . $this->currentWindow();
        Cache::increment($key, $count);
        Cache::put($key . '.ttl', 1, self::WINDOW_SECONDS * 2);
    }

    public function requestsInWindow(string $scope): int
    {
        return (int) Cache::get(self::RATE_KEY_PREFIX . $scope . '.' . $this->currentWindow(), 0);
    }

    public function exceeds(string $scope, int $limit): bool
    {
        return $this->requestsInWindow($scope) > $limit;
    }

    public function shouldShed(string $scope, int $limit, float $shedFactor = 0.2): bool
    {
        if (!$this->exceeds($scope, $limit)) {
            return false;
        }

        // Probabilistic load shedding — shed $shedFactor% of excess requests
        return (mt_rand(0, 99) / 100) < $shedFactor;
    }

    private function currentWindow(): int
    {
        return (int) floor(time() / self::WINDOW_SECONDS);
    }
}
