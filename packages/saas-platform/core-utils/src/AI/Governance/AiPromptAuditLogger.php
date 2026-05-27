<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiPromptAuditLogger
{
    private const LOG_KEY_PREFIX  = 'ai:audit:prompts:';
    private const MAX_LOG_ENTRIES = 500;

    public function logRequest(
        string $tenantId,
        string $domain,
        string $promptKey,
        string $provider,
        bool   $success,
        int    $tokens = 0
    ): void {
        if (! config('ai.security.audit_prompts', true)) {
            return;
        }

        $this->appendLog($tenantId, [
            'type'       => 'request',
            'domain'     => $domain,
            'prompt_key' => $promptKey,
            'provider'   => $provider,
            'success'    => $success,
            'tokens'     => $tokens,
            'ts'         => now()->toISOString(),
        ]);
    }

    public function logInjectionAttempt(string $prompt, string $tenantId, array $detection): void
    {
        $this->appendLog($tenantId, [
            'type'         => 'injection_attempt',
            'risk'         => $detection['risk'],
            'pattern_count'=> count($detection['patterns']),
            'prompt_hash'  => md5($prompt),
            'ts'           => now()->toISOString(),
        ]);
    }

    public function logResponse(
        string $tenantId,
        string $domain,
        string $provider,
        bool   $accepted,
        ?string $reason = null
    ): void {
        if (! config('ai.security.audit_responses', false)) {
            return;
        }

        $this->appendLog($tenantId, [
            'type'     => 'response',
            'domain'   => $domain,
            'provider' => $provider,
            'accepted' => $accepted,
            'reason'   => $reason,
            'ts'       => now()->toISOString(),
        ]);
    }

    public function getLog(string $tenantId, int $limit = 100): array
    {
        return array_slice(
            Cache::get(self::LOG_KEY_PREFIX . $tenantId, []),
            -$limit
        );
    }

    public function getInjectionAttempts(string $tenantId): array
    {
        return array_values(array_filter(
            $this->getLog($tenantId, 500),
            fn ($e) => ($e['type'] ?? '') === 'injection_attempt'
        ));
    }

    public function clearLog(string $tenantId): void
    {
        Cache::forget(self::LOG_KEY_PREFIX . $tenantId);
    }

    private function appendLog(string $tenantId, array $entry): void
    {
        $key  = self::LOG_KEY_PREFIX . $tenantId;
        $ttl  = config('ai.audit.log_ttl_days', 30) * 86400;
        $log  = Cache::get($key, []);
        $log[] = $entry;

        if (count($log) > self::MAX_LOG_ENTRIES) {
            $log = array_slice($log, -self::MAX_LOG_ENTRIES);
        }

        Cache::put($key, $log, $ttl);
    }
}
