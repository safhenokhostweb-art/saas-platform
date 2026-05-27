<?php

namespace SaaSPlatform\CoreUtils\AI\Observability;

use Illuminate\Support\Facades\Cache;

class AiDecisionAuditLog
{
    private const LOG_KEY  = 'ai:audit:decisions:';
    private const MAX_SIZE = 500;
    private const TTL_DAYS = 30;

    public function log(
        string  $tenantId,
        string  $domain,
        string  $operation,
        string  $decision,
        string  $provider,
        bool    $accepted,
        ?string $rejectionReason = null,
        array   $meta = []
    ): void {
        $key     = self::LOG_KEY . $tenantId;
        $entries = Cache::get($key, []);

        $entries[] = [
            'tenant_id'        => $tenantId,
            'domain'           => $domain,
            'operation'        => $operation,
            'decision_hash'    => md5($decision),
            'provider'         => $provider,
            'accepted'         => $accepted,
            'rejection_reason' => $rejectionReason,
            'meta'             => $meta,
            'ts'               => now()->toISOString(),
        ];

        if (count($entries) > self::MAX_SIZE) {
            $entries = array_slice($entries, -self::MAX_SIZE);
        }

        Cache::put($key, $entries, 86400 * self::TTL_DAYS);
    }

    public function getLog(string $tenantId, int $limit = 100): array
    {
        return array_slice(
            array_reverse(Cache::get(self::LOG_KEY . $tenantId, [])),
            0,
            $limit
        );
    }

    public function getAcceptanceRate(string $tenantId, string $domain = ''): array
    {
        $entries = Cache::get(self::LOG_KEY . $tenantId, []);
        if ($domain) {
            $entries = array_filter($entries, fn ($e) => $e['domain'] === $domain);
        }

        $total    = count($entries);
        $accepted = count(array_filter($entries, fn ($e) => $e['accepted']));

        return [
            'total'    => $total,
            'accepted' => $accepted,
            'rejected' => $total - $accepted,
            'rate'     => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
        ];
    }

    public function getRejectionReasons(string $tenantId): array
    {
        $entries = Cache::get(self::LOG_KEY . $tenantId, []);
        $reasons = [];
        foreach ($entries as $entry) {
            if (! $entry['accepted'] && $entry['rejection_reason']) {
                $r = $entry['rejection_reason'];
                $reasons[$r] = ($reasons[$r] ?? 0) + 1;
        }
        }
        arsort($reasons);
        return $reasons;
    }
}
