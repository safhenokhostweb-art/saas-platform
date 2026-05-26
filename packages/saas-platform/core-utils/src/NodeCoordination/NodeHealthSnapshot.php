<?php

namespace SaaSPlatform\CoreUtils\NodeCoordination;

class NodeHealthSnapshot
{
    public function __construct(
        public readonly string  $nodeId,
        public readonly string  $hostname,
        public readonly string  $role,
        public readonly int     $memoryUsageMb,
        public readonly float   $memoryUsagePct,
        public readonly int     $activeJobs,
        public readonly string  $status,
        public readonly string  $capturedAt
    ) {}

    public static function capture(string $nodeId, array $metadata = []): self
    {
        $memUsage    = memory_get_usage(true);
        $memLimit    = ini_get('memory_limit');
        $limitBytes  = self::parseMemoryLimit($memLimit);
        $usagePct    = $limitBytes > 0 ? round($memUsage / $limitBytes * 100, 1) : 0.0;

        return new self(
            nodeId:         $nodeId,
            hostname:       $metadata['hostname'] ?? gethostname(),
            role:           $metadata['role'] ?? 'worker',
            memoryUsageMb:  (int) round($memUsage / 1024 / 1024),
            memoryUsagePct: $usagePct,
            activeJobs:     $metadata['active_jobs'] ?? 0,
            status:         $usagePct > 85 ? 'degraded' : 'healthy',
            capturedAt:     now()->toIso8601String()
        );
    }

    public function toArray(): array
    {
        return [
            'node_id'          => $this->nodeId,
            'hostname'         => $this->hostname,
            'role'             => $this->role,
            'memory_usage_mb'  => $this->memoryUsageMb,
            'memory_usage_pct' => $this->memoryUsagePct,
            'active_jobs'      => $this->activeJobs,
            'status'           => $this->status,
            'captured_at'      => $this->capturedAt,
        ];
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    private static function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return 0;
        }
        $unit  = strtoupper(substr($limit, -1));
        $value = (int) $limit;
        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
