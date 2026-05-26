<?php

namespace SaaSPlatform\CoreUtils\NodeCoordination;

use Illuminate\Support\Facades\Cache;

class NodeCapacityTracker
{
    private const CAPACITY_PREFIX = 'node.capacity.';
    private const CAPACITY_TTL   = 120;

    public function report(string $nodeId, array $capacity): void
    {
        Cache::put(self::CAPACITY_PREFIX . $nodeId, array_merge($capacity, [
            'node_id'     => $nodeId,
            'reported_at' => now()->toIso8601String(),
        ]), self::CAPACITY_TTL);
    }

    public function get(string $nodeId): ?array
    {
        return Cache::get(self::CAPACITY_PREFIX . $nodeId);
    }

    public function reportCurrent(string $nodeId, int $activeJobs = 0, int $maxJobs = 10): void
    {
        $this->report($nodeId, [
            'active_jobs'     => $activeJobs,
            'max_jobs'        => $maxJobs,
            'utilization_pct' => $maxJobs > 0 ? round($activeJobs / $maxJobs * 100, 1) : 0,
            'memory_mb'       => round(memory_get_usage(true) / 1024 / 1024),
        ]);
    }

    public function utilizationPct(string $nodeId): float
    {
        return (float) ($this->get($nodeId)['utilization_pct'] ?? 0.0);
    }

    public function isOverloaded(string $nodeId, float $threshold = 80.0): bool
    {
        return $this->utilizationPct($nodeId) >= $threshold;
    }

    public function leastLoadedNode(array $nodeIds): ?string
    {
        $best     = null;
        $bestPct  = PHP_FLOAT_MAX;

        foreach ($nodeIds as $nodeId) {
            $pct = $this->utilizationPct($nodeId);
            if ($pct < $bestPct) {
                $bestPct = $pct;
                $best    = $nodeId;
            }
        }

        return $best;
    }

    public function clusterUtilization(array $nodeIds): float
    {
        if (empty($nodeIds)) {
            return 0.0;
        }
        $total = array_sum(array_map(fn($id) => $this->utilizationPct($id), $nodeIds));
        return round($total / count($nodeIds), 1);
    }
}
