<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Scalability;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Scalability\TenantStressSimulator;
use SaaSPlatform\CoreUtils\CacheGovernance\DistributedCacheIsolation;
use SaaSPlatform\CoreUtils\StorageGovernance\AttachmentIsolationManager;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\RuntimeFairnessCoordinator;

class TestIsolationCommand extends Command
{
    protected $signature = 'scalability:test-isolation
                            {--tenant=1 : Tenant ID to test}
                            {--victims=* : Victim tenant IDs for noisy-neighbor test}
                            {--json      : Output as JSON}';

    protected $description = 'Test tenant isolation and noisy-neighbor resistance';

    public function handle(
        TenantStressSimulator      $stressSimulator,
        DistributedCacheIsolation  $cacheIsolation,
        AttachmentIsolationManager $attachmentIsolation,
        RuntimeFairnessCoordinator $fairness
    ): int {
        $tenantId  = (int) $this->option('tenant');
        $victimIds = array_map('intval', $this->option('victims') ?: []);

        $stress     = $stressSimulator->simulateLoad($tenantId, ['dry_run' => true]);
        $noisyTest  = !empty($victimIds)
            ? $stressSimulator->simulateNoisyNeighbor($tenantId, $victimIds)
            : null;

        $cacheIsolationTest    = $cacheIsolation->isCrossTenatAccess('tenant.' . ($tenantId + 1) . ':key', $tenantId);
        $storageIsolationTest  = $attachmentIsolation->isCrossTenantAccess($tenantId, 'tenants/' . ($tenantId + 1) . '/file.pdf');
        $fairnessScore         = $fairness->fairnessScore(array_merge([$tenantId], $victimIds));

        $result = [
            'tenant_id'             => $tenantId,
            'stress_test'           => $stress,
            'noisy_neighbor_test'   => $noisyTest,
            'cache_isolation_works' => $cacheIsolationTest,
            'storage_isolation_works' => $storageIsolationTest,
            'fairness_score'        => $fairnessScore,
            'isolation_grade'       => $fairnessScore >= 80 ? 'PASS' : ($fairnessScore >= 60 ? 'WARN' : 'FAIL'),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $fairnessScore >= 60 ? 0 : 1;
        }

        $this->info('=== Tenant Isolation Test (Tenant #' . $tenantId . ') ===');
        $this->line('Pressure Level : ' . $stress['pressure_level']);
        $this->line('Fairness Score : ' . $fairnessScore);
        $this->line('Cache Isolation: ' . ($cacheIsolationTest ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));
        $this->line('Storage Isolation: ' . ($storageIsolationTest ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));

        if ($noisyTest !== null) {
            $this->newLine();
            $this->info('Noisy-Neighbor Test:');
            $this->line('  Noisy Tenant Pressure: ' . $noisyTest['noisy_tenant']['pressure']);
            $this->line('  Isolation Holds: ' . ($noisyTest['isolation_holds'] ? 'Yes' : 'No'));
        }

        $this->newLine();
        $grade = $result['isolation_grade'];
        $color = match ($grade) { 'PASS' => 'green', 'WARN' => 'yellow', default => 'red' };
        $this->line("Isolation Grade: <fg={$color}>{$grade}</>");

        return $fairnessScore >= 60 ? 0 : 1;
    }
}
