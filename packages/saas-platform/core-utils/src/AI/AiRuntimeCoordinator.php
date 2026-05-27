<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use SaaSPlatform\CoreUtils\AI\Governance\PromptSecuritySanitizer;
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\CoreUtils\AI\Governance\AiRateLimitCoordinator;
use SaaSPlatform\CoreUtils\AI\Observability\AiTelemetryService;

class AiRuntimeCoordinator
{
    public function __construct(
        private readonly AiProviderManager        $providerManager,
        private readonly AiFallbackCoordinator    $fallbackCoordinator,
        private readonly AiProviderHealthMonitor  $healthMonitor,
        private readonly PromptSecuritySanitizer  $sanitizer,
        private readonly TenantAiQuotaManager     $quotaManager,
        private readonly AiRateLimitCoordinator   $rateLimiter,
        private readonly AiTelemetryService       $telemetry,
    ) {}

    /**
     * Execute a governed AI completion request.
     *
     * All requests pass through: quota → rate limit → sanitize → execute → telemetry
     */
    public function complete(
        string $prompt,
        string $tenantId = '',
        string $domain = 'general',
        array  $options = [],
        array  $fallbackArgs = []
    ): AiCompletionResult {
        return $this->completeChat(
            [['role' => 'user', 'content' => $prompt]],
            $tenantId,
            $domain,
            $options,
            $fallbackArgs
        );
    }

    public function completeChat(
        array  $messages,
        string $tenantId = '',
        string $domain = 'general',
        array  $options = [],
        array  $fallbackArgs = []
    ): AiCompletionResult {
        // Emergency kill switch
        if (config('ai.emergency.kill_switch', false)) {
            return $this->fallbackCoordinator->execute($domain, $messages, $options, $fallbackArgs);
        }

        $start = microtime(true);

        // Quota check (non-blocking — warns but doesn't hard-block by default)
        $quotaResult = $tenantId ? $this->quotaManager->check($tenantId) : ['allowed' => true];
        if (! $quotaResult['allowed']) {
            $this->telemetry->recordQuotaBlock($tenantId, $domain);
            return $this->fallbackCoordinator->execute($domain, $messages, $options, $fallbackArgs);
        }

        // Rate limit check
        if ($tenantId && ! $this->rateLimiter->allow($tenantId)) {
            $this->telemetry->recordRateLimit($tenantId, $domain);
            return $this->fallbackCoordinator->execute($domain, $messages, $options, $fallbackArgs);
        }

        // Sanitize prompts
        $messages = $this->sanitizer->sanitizeMessages($messages, $tenantId);

        // Execute with fallback chain
        $result = $this->fallbackCoordinator->execute($domain, $messages, $options, $fallbackArgs);

        $latencyMs = round((microtime(true) - $start) * 1000, 1);
        $provider  = $result->meta['provider'] ?? 'fallback';

        // Record telemetry
        $this->telemetry->record([
            'tenant_id'    => $tenantId,
            'domain'       => $domain,
            'provider'     => $provider,
            'success'      => $result->success,
            'latency_ms'   => $latencyMs,
            'tokens'       => $result->meta['total_tokens'] ?? 0,
            'source'       => $result->meta['source'] ?? 'provider',
        ]);

        // Record health metrics
        if ($provider !== 'fallback') {
            $this->healthMonitor->recordLatency($provider, $latencyMs);
            $this->healthMonitor->recordRequest($provider, $result->success);
        }

        // Increment quota usage
        if ($tenantId && $result->success && ($result->meta['source'] ?? '') === 'provider') {
            $this->quotaManager->increment($tenantId);
        }

        return $result;
    }

    public function isAvailable(): bool
    {
        return $this->providerManager->isAnyAvailable()
            && ! config('ai.emergency.kill_switch', false)
            && ! config('ai.emergency.degraded_mode', false);
    }

    public function getStatus(): array
    {
        return [
            'available'      => $this->isAvailable(),
            'providers'      => $this->providerManager->getStatus(),
            'kill_switch'    => config('ai.emergency.kill_switch', false),
            'degraded_mode'  => config('ai.emergency.degraded_mode', false),
        ];
    }
}
