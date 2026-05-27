<?php

namespace SaaSPlatform\CoreUtils\AI\Observability;

use Illuminate\Support\Facades\Cache;

class AiFailureAnalytics
{
    private const FAILURE_KEY = 'ai:failures:';
    private const MAX_ENTRIES = 300;

    public function record(string $tenantId, string $provider, string $domain, string $error, array $context = []): void
    {
        $key      = self::FAILURE_KEY . date('Y-m-d');
        $failures = Cache::get($key, []);

        $failures[] = [
            'tenant_id' => $tenantId,
            'provider'  => $provider,
            'domain'    => $domain,
            'error'     => $error,
            'error_type'=> $this->classifyError($error),
            'context'   => $context,
            'ts'        => now()->toISOString(),
        ];

        if (count($failures) > self::MAX_ENTRIES) {
            $failures = array_slice($failures, -self::MAX_ENTRIES);
        }

        Cache::put($key, $failures, 86400 * 7);
    }

    public function getFailureSummary(int $days = 7): array
    {
        $summary = ['total' => 0, 'by_provider' => [], 'by_type' => [], 'by_domain' => []];

        for ($i = 0; $i < $days; $i++) {
            $date     = date('Y-m-d', strtotime("-{$i} days"));
            $failures = Cache::get(self::FAILURE_KEY . $date, []);

            foreach ($failures as $f) {
                $summary['total']++;
                $p = $f['provider'] ?? 'unknown';
                $t = $f['error_type'] ?? 'unknown';
                $d = $f['domain'] ?? 'unknown';

                $summary['by_provider'][$p] = ($summary['by_provider'][$p] ?? 0) + 1;
                $summary['by_type'][$t]     = ($summary['by_type'][$t] ?? 0) + 1;
                $summary['by_domain'][$d]   = ($summary['by_domain'][$d] ?? 0) + 1;
            }
        }

        arsort($summary['by_provider']);
        arsort($summary['by_type']);
        arsort($summary['by_domain']);

        return $summary;
    }

    public function getHourlyFailureRate(): array
    {
        $today    = Cache::get(self::FAILURE_KEY . date('Y-m-d'), []);
        $byHour   = array_fill(0, 24, 0);

        foreach ($today as $f) {
            $hour = (int) date('G', strtotime($f['ts'] ?? ''));
            $byHour[$hour]++;
        }

        return $byHour;
    }

    private function classifyError(string $error): string
    {
        return match(true) {
            str_contains($error, 'timeout') || str_contains($error, 'connection') => 'network',
            str_contains($error, '429') || str_contains($error, 'rate') => 'rate_limit',
            str_contains($error, '401') || str_contains($error, 'key') || str_contains($error, 'auth') => 'auth',
            str_contains($error, '500') || str_contains($error, '503') => 'server_error',
            str_contains($error, 'quota') || str_contains($error, 'budget') => 'quota',
            str_contains($error, 'context') || str_contains($error, 'token') => 'context_length',
            default => 'general',
        };
    }
}
