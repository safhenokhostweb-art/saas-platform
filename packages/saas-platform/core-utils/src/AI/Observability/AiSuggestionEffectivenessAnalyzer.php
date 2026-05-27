<?php

namespace SaaSPlatform\CoreUtils\AI\Observability;

use Illuminate\Support\Facades\Cache;

class AiSuggestionEffectivenessAnalyzer
{
    private const FEEDBACK_KEY = 'ai:effectiveness:';
    private const MAX_ENTRIES  = 500;

    public function recordAccepted(string $tenantId, string $domain, string $suggestionHash, array $meta = []): void
    {
        $this->recordFeedback($tenantId, $domain, $suggestionHash, 'accepted', $meta);
    }

    public function recordRejected(string $tenantId, string $domain, string $suggestionHash, ?string $reason = null, array $meta = []): void
    {
        $this->recordFeedback($tenantId, $domain, $suggestionHash, 'rejected', array_merge($meta, ['reason' => $reason]));
    }

    public function recordEdited(string $tenantId, string $domain, string $suggestionHash, float $editDistance, array $meta = []): void
    {
        $this->recordFeedback($tenantId, $domain, $suggestionHash, 'edited', array_merge($meta, ['edit_distance' => $editDistance]));
    }

    public function getEffectivenessReport(string $tenantId, int $days = 30): array
    {
        $feedback = $this->getFeedback($tenantId, $days);

        $total    = count($feedback);
        $accepted = count(array_filter($feedback, fn ($f) => $f['outcome'] === 'accepted'));
        $rejected = count(array_filter($feedback, fn ($f) => $f['outcome'] === 'rejected'));
        $edited   = count(array_filter($feedback, fn ($f) => $f['outcome'] === 'edited'));

        $byDomain = [];
        foreach ($feedback as $f) {
            $d = $f['domain'] ?? 'general';
            if (! isset($byDomain[$d])) {
                $byDomain[$d] = ['total' => 0, 'accepted' => 0];
            }
            $byDomain[$d]['total']++;
            if ($f['outcome'] === 'accepted') $byDomain[$d]['accepted']++;
        }

        foreach ($byDomain as $d => &$stats) {
            $stats['rate'] = $stats['total'] > 0
                ? round(($stats['accepted'] / $stats['total']) * 100, 1)
                : 0;
        }

        return [
            'total'         => $total,
            'accepted'      => $accepted,
            'rejected'      => $rejected,
            'edited'        => $edited,
            'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
            'by_domain'     => $byDomain,
            'days'          => $days,
        ];
    }

    private function recordFeedback(string $tenantId, string $domain, string $hash, string $outcome, array $meta): void
    {
        $key      = self::FEEDBACK_KEY . $tenantId;
        $feedback = Cache::get($key, []);
        $feedback[] = [
            'domain'  => $domain,
            'hash'    => $hash,
            'outcome' => $outcome,
            'meta'    => $meta,
            'ts'      => now()->toISOString(),
        ];

        if (count($feedback) > self::MAX_ENTRIES) {
            $feedback = array_slice($feedback, -self::MAX_ENTRIES);
        }

        Cache::put($key, $feedback, 86400 * 31);
    }

    private function getFeedback(string $tenantId, int $days): array
    {
        $cutoff   = now()->subDays($days)->toISOString();
        $feedback = Cache::get(self::FEEDBACK_KEY . $tenantId, []);
        return array_filter($feedback, fn ($f) => ($f['ts'] ?? '') >= $cutoff);
    }
}
