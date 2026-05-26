<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker;

use Illuminate\Support\Facades\Cache;

class RuntimeCircuit
{
    public const STATE_CLOSED    = 'CLOSED';
    public const STATE_OPEN      = 'OPEN';
    public const STATE_HALF_OPEN = 'HALF_OPEN';

    private const DEFAULT_FAILURE_THRESHOLD  = 5;
    private const DEFAULT_RECOVERY_TIMEOUT   = 60;  // seconds before HALF_OPEN
    private const DEFAULT_SUCCESS_THRESHOLD  = 2;   // successes to close from HALF_OPEN

    public function __construct(
        private string $key,
        private int    $failureThreshold  = self::DEFAULT_FAILURE_THRESHOLD,
        private int    $recoveryTimeout   = self::DEFAULT_RECOVERY_TIMEOUT,
        private int    $successThreshold  = self::DEFAULT_SUCCESS_THRESHOLD
    ) {}

    public function call(callable $fn): mixed
    {
        $state = $this->state();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptHalfOpen()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
            } else {
                throw new \RuntimeException("Circuit '{$this->key}' is OPEN — calls rejected");
            }
        }

        try {
            $result = $fn();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function state(): string
    {
        return Cache::get($this->cacheKey('state'), self::STATE_CLOSED);
    }

    public function recordFailure(): void
    {
        $failures = (int) Cache::increment($this->cacheKey('failures'));
        Cache::put($this->cacheKey('last_failure_at'), time(), $this->recoveryTimeout * 10);

        if ($failures >= $this->failureThreshold && $this->state() === self::STATE_CLOSED) {
            $this->transitionTo(self::STATE_OPEN);
        }

        if ($this->state() === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_OPEN);
        }
    }

    public function recordSuccess(): void
    {
        $state = $this->state();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = (int) Cache::increment($this->cacheKey('half_open_successes'));
            if ($successes >= $this->successThreshold) {
                $this->reset();
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure counter on consecutive successes
            Cache::forget($this->cacheKey('failures'));
        }
    }

    public function reset(): void
    {
        Cache::forget($this->cacheKey('state'));
        Cache::forget($this->cacheKey('failures'));
        Cache::forget($this->cacheKey('last_failure_at'));
        Cache::forget($this->cacheKey('half_open_successes'));
    }

    public function forceOpen(): void
    {
        $this->transitionTo(self::STATE_OPEN);
    }

    public function failureCount(): int
    {
        return (int) Cache::get($this->cacheKey('failures'), 0);
    }

    public function lastFailureAt(): ?int
    {
        return Cache::get($this->cacheKey('last_failure_at'));
    }

    public function toArray(): array
    {
        return [
            'key'              => $this->key,
            'state'            => $this->state(),
            'failure_count'    => $this->failureCount(),
            'last_failure_at'  => $this->lastFailureAt(),
            'threshold'        => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
        ];
    }

    private function transitionTo(string $state): void
    {
        Cache::forever($this->cacheKey('state'), $state);

        if ($state === self::STATE_OPEN) {
            Cache::put($this->cacheKey('opened_at'), time(), $this->recoveryTimeout * 10);
        }
    }

    private function shouldAttemptHalfOpen(): bool
    {
        $openedAt = Cache::get($this->cacheKey('opened_at'));
        return $openedAt && (time() - $openedAt) >= $this->recoveryTimeout;
    }

    private function cacheKey(string $part): string
    {
        return 'governance.circuit.' . $this->key . '.' . $part;
    }
}
