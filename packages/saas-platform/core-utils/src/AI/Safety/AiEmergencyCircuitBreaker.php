<?php

namespace SaaSPlatform\CoreUtils\AI\Safety;

use Illuminate\Support\Facades\Cache;

class AiEmergencyCircuitBreaker
{
    private const CIRCUIT_KEY   = 'ai:circuit:state';
    private const FAILURE_KEY   = 'ai:circuit:failures';
    private const THRESHOLD     = 10;
    private const RESET_AFTER   = 300; // seconds

    public function isOpen(): bool
    {
        if (config('ai.emergency.kill_switch', false)) {
            return true;
        }

        $state = Cache::get(self::CIRCUIT_KEY, 'closed');
        if ($state === 'open') {
            $openedAt = Cache::get(self::CIRCUIT_KEY.':opened_at', 0);
            if (time() - $openedAt > self::RESET_AFTER) {
                Cache::put(self::CIRCUIT_KEY, 'half-open', now()->addMinutes(10));
                return false;
            }
            return true;
        }

        return false;
    }

    public function recordSuccess(): void
    {
        $state = Cache::get(self::CIRCUIT_KEY, 'closed');
        if ($state === 'half-open') {
            $this->close();
        }
    }

    public function recordFailure(): void
    {
        $failures = (int) Cache::get(self::FAILURE_KEY, 0);
        $failures++;
        Cache::put(self::FAILURE_KEY, $failures, now()->addMinutes(10));

        if ($failures >= self::THRESHOLD) {
            $this->open();
        }
    }

    public function open(): void
    {
        Cache::put(self::CIRCUIT_KEY, 'open', now()->addHours(1));
        Cache::put(self::CIRCUIT_KEY.':opened_at', time(), now()->addHours(1));
        Cache::put(self::FAILURE_KEY, 0, now()->addMinutes(10));
    }

    public function close(): void
    {
        Cache::put(self::CIRCUIT_KEY, 'closed', now()->addHours(1));
        Cache::put(self::FAILURE_KEY, 0, now()->addMinutes(10));
    }

    public function state(): string
    {
        if (config('ai.emergency.kill_switch', false)) {
            return 'kill_switch';
        }
        return Cache::get(self::CIRCUIT_KEY, 'closed');
    }

    public function status(): array
    {
        return [
            'state'         => $this->state(),
            'failures'      => (int) Cache::get(self::FAILURE_KEY, 0),
            'threshold'     => self::THRESHOLD,
            'reset_after_s' => self::RESET_AFTER,
            'kill_switch'   => (bool) config('ai.emergency.kill_switch', false),
        ];
    }
}
