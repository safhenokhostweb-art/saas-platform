<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Security;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

class AuthFailureTelemetry
{
    private const COUNTER_PREFIX  = 'telemetry.security.auth_fail.';
    private const WINDOW_SECONDS  = 900; // 15 min

    public function __construct(
        private TelemetryManager $telemetry,
        private TelemetryContext $context
    ) {}

    public function record(string $reason, array $safeContext = []): void
    {
        $tenantId = $this->context->getTenantId();

        // Increment per-tenant and global counters
        Cache::increment(self::COUNTER_PREFIX . 'global');
        if ($tenantId) {
            Cache::increment(self::COUNTER_PREFIX . 'tenant.' . $tenantId);
        }

        $this->telemetry->warning('security', 'security.auth.failure', array_merge([
            'reason'    => $reason,
            'tenant_id' => $tenantId,
        ], $safeContext));
    }

    public function globalCount(): int
    {
        return (int) Cache::get(self::COUNTER_PREFIX . 'global', 0);
    }

    public function tenantCount(int $tenantId): int
    {
        return (int) Cache::get(self::COUNTER_PREFIX . 'tenant.' . $tenantId, 0);
    }

    public function resetGlobal(): void
    {
        Cache::forget(self::COUNTER_PREFIX . 'global');
    }
}
