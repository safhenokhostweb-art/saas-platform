<?php

namespace SaaSPlatform\Modules\Education\Ai\Providers;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;

/**
 * Circuit-breaker decorator for any AiContentProviderInterface.
 *
 * State machine:
 *
 *   CLOSED    — Normal operation. Failures are counted within the rolling
 *               recovery_window_seconds window.
 *
 *   OPEN      — Failure threshold reached. All requests are rejected immediately
 *               without calling the inner provider. The circuit stays OPEN for
 *               open_ttl_seconds.
 *
 *   HALF_OPEN — Inferred: the OPEN TTL has expired but failures still exist in
 *               the recovery window. The first request is allowed through as a
 *               probe. Success → CLOSED. Failure → OPEN again.
 *
 * Cache keys (namespaced per inner provider name):
 *   edu:ai:cb:{name}:failures  — rolling failure count (TTL = recovery_window_seconds)
 *   edu:ai:cb:{name}:open      — circuit-open flag    (TTL = open_ttl_seconds)
 *
 * All thresholds are read exclusively from config('education.ai.circuit_breaker.*').
 * No hardcoded fallbacks beyond what config() itself provides (see education.php).
 *
 * -------------------------------------------------------------------------
 * TenantAiQuotaManager — integration point (NOT implemented in Phase 3)
 * -------------------------------------------------------------------------
 * Location: SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager
 * Container key: injectable via constructor or $app->make(TenantAiQuotaManager::class)
 *
 * Integration point: inside generate(), BEFORE delegating to $this->inner:
 *
 *   $tenantKey = 'edu_site_' . ($job->siteId ?? 'platform');
 *   $quota = $quotaManager->check($tenantKey);
 *   if (! $quota['allowed']) {
 *       throw new AiProviderException("Quota exceeded for {$tenantKey}.");
 *   }
 *   // ... call inner ...
 *   $quotaManager->increment($tenantKey);
 *   $quotaManager->incrementSystemTotal();
 *
 * Defer to a dedicated quota phase or Phase 5+.
 */
class CircuitBreakerAiContentProvider implements AiContentProviderInterface
{
    private const KEY_FAILURES = 'edu:ai:cb:%s:failures';
    private const KEY_OPEN     = 'edu:ai:cb:%s:open';

    public function __construct(
        private readonly AiContentProviderInterface $inner,
        private readonly ?TenantAiQuotaManager      $quotaManager = null,
    ) {}

    // -------------------------------------------------------------------------
    // AiContentProviderInterface
    // -------------------------------------------------------------------------

    public function generate(ContentGenerationJobDto $job): GeneratedContentDto
    {
        // 1. Per-tenant quota check — must happen before the circuit to avoid
        //    counting quota failures as circuit-breaker failures.
        $tenantKey = $this->resolveQuotaKey($job);
        if ($tenantKey !== null) {
            $quota = $this->quotaManager->check($tenantKey);
            if (! $quota['allowed']) {
                throw new AiProviderException(sprintf(
                    'AI quota exceeded for "%s". Daily limit: %s, used: %d.',
                    $tenantKey,
                    $quota['limit'],
                    $quota['used'],
                ));
            }
        }

        // 2. Circuit-breaker guard
        if (! $this->circuitEnabled()) {
            $result = $this->inner->generate($job);
            $this->trackQuotaUsage($tenantKey);
            return $result;
        }

        if ($this->isOpen()) {
            throw new AiProviderException(sprintf(
                'AI provider circuit is OPEN for "%s" — too many recent failures. '
                . 'Circuit resets after %d seconds.',
                $this->inner->providerName(),
                (int) config('education.ai.circuit_breaker.open_ttl_seconds'),
            ));
        }

        try {
            $result = $this->inner->generate($job);
        } catch (AiProviderException $e) {
            $this->recordFailure();
            throw $e;
        }

        $this->recordSuccess();
        $this->trackQuotaUsage($tenantKey);

        return $result;
    }

    public function isEnabled(): bool
    {
        return $this->inner->isEnabled();
    }

    public function providerName(): string
    {
        return $this->inner->providerName();
    }

    // -------------------------------------------------------------------------
    // Circuit state — public for diagnostics / artisan commands
    // -------------------------------------------------------------------------

    public function getState(): string
    {
        if ($this->isOpen()) {
            return 'OPEN';
        }

        if ((int) Cache::get($this->key(self::KEY_FAILURES), 0) > 0) {
            return 'HALF_OPEN';
        }

        return 'CLOSED';
    }

    public function isOpen(): bool
    {
        return (bool) Cache::get($this->key(self::KEY_OPEN), false);
    }

    public function reset(): void
    {
        Cache::forget($this->key(self::KEY_FAILURES));
        Cache::forget($this->key(self::KEY_OPEN));
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function recordFailure(): void
    {
        $failuresKey    = $this->key(self::KEY_FAILURES);
        $recoveryWindow = (int) config('education.ai.circuit_breaker.recovery_window_seconds');
        $threshold      = (int) config('education.ai.circuit_breaker.failure_threshold');

        $failures = (int) Cache::get($failuresKey, 0) + 1;
        Cache::put($failuresKey, $failures, $recoveryWindow);

        if ($failures >= $threshold) {
            Cache::put(
                $this->key(self::KEY_OPEN),
                true,
                (int) config('education.ai.circuit_breaker.open_ttl_seconds'),
            );
        }
    }

    private function recordSuccess(): void
    {
        Cache::forget($this->key(self::KEY_FAILURES));
        Cache::forget($this->key(self::KEY_OPEN));
    }

    private function circuitEnabled(): bool
    {
        return (bool) config('education.ai.circuit_breaker.enabled', true);
    }

    private function key(string $template): string
    {
        return sprintf($template, $this->inner->providerName());
    }

    // ── Quota helpers ─────────────────────────────────────────────────────────

    private function resolveQuotaKey(ContentGenerationJobDto $job): ?string
    {
        if ($this->quotaManager === null) {
            return null;
        }
        if (! config('education.ai.quota.enabled', false)) {
            return null;
        }
        return 'edu_site_' . ($job->siteId ?? 'platform');
    }

    private function trackQuotaUsage(?string $tenantKey): void
    {
        if ($tenantKey === null || $this->quotaManager === null) {
            return;
        }
        $this->quotaManager->increment($tenantKey);
        $this->quotaManager->incrementSystemTotal();
    }
}
