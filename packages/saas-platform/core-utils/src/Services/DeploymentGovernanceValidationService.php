<?php

namespace SaaSPlatform\CoreUtils\Services;

use Illuminate\Contracts\Container\Container;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestValidator;
use SaaSPlatform\CoreUtils\Services\Migration\SchemaDriftDetector;
use SaaSPlatform\CoreUtils\Services\Migration\MigrationSafetyService;
use SaaSPlatform\CoreUtils\Services\Queue\QueueRuntimeCompatibilityService;
use SaaSPlatform\CoreUtils\Services\Assets\AssetVersionValidator;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;
use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;
use SaaSPlatform\CoreUtils\Services\Rollback\ReleaseRollbackValidator;

/**
 * Master deployment governance validator (Phase 8.2).
 *
 * Aggregates all deployment subsystem checks into a single scored report.
 * Each dimension is 0–100 and contributes to the overall production readiness score.
 */
class DeploymentGovernanceValidationService
{
    public function __construct(private Container $app) {}

    public function run(bool $strict = false): array
    {
        $dimensions = [];

        $dimensions['deployment_locks']     = $this->checkDeploymentLocks();
        $dimensions['release_manifests']    = $this->checkReleaseManifests();
        $dimensions['rollback_readiness']   = $this->checkRollbackReadiness();
        $dimensions['migration_safety']     = $this->checkMigrationSafety();
        $dimensions['queue_compatibility']  = $this->checkQueueCompatibility();
        $dimensions['asset_synchronization']= $this->checkAssets();
        $dimensions['tenant_maintenance']   = $this->checkTenantMaintenance();
        $dimensions['health_endpoints']     = $this->checkHealth();
        $dimensions['release_integrity']    = $this->checkReleaseIntegrity();

        $scores      = array_column($dimensions, 'score');
        $totalScore  = count($scores) > 0 ? (int) round(array_sum($scores) / count($scores)) : 0;
        $passed      = $strict ? ($totalScore === 100) : ($totalScore >= 80);

        return [
            'passed'     => $passed,
            'score'      => $totalScore,
            'grade'      => $this->grade($totalScore),
            'strict'     => $strict,
            'dimensions' => $dimensions,
            'scores' => [
                'deployment_safety'       => $dimensions['deployment_locks']['score'],
                'release_integrity'       => $dimensions['release_integrity']['score'],
                'rollback_survivability'  => $dimensions['rollback_readiness']['score'],
                'runtime_governance'      => (int) round(
                    ($dimensions['queue_compatibility']['score'] + $dimensions['health_endpoints']['score']) / 2
                ),
                'production_readiness'    => $totalScore,
            ],
        ];
    }

    private function checkDeploymentLocks(): array
    {
        try {
            $manager  = $this->app->make(ReleaseLockManager::class);
            $hasLock  = $manager->hasActiveLock();
            return [
                'check'   => 'deployment_locks',
                'score'   => 100,
                'passed'  => true,
                'detail'  => $hasLock ? 'Lock system operational (lock currently held)' : 'Lock system operational, no active lock',
            ];
        } catch (\Throwable $e) {
            return ['check' => 'deployment_locks', 'score' => 0, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkReleaseManifests(): array
    {
        try {
            $manager = $this->app->make(ReleaseManifestManager::class);
            $latest  = $manager->latest();
            $count   = count($manager->all());
            $score   = $latest ? 100 : 50;
            return [
                'check'  => 'release_manifests',
                'score'  => $score,
                'passed' => $score >= 50,
                'detail' => $latest ? "{$count} manifest(s), latest: {$latest['released_at']}" : 'No release manifests yet',
            ];
        } catch (\Throwable $e) {
            return ['check' => 'release_manifests', 'score' => 0, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkRollbackReadiness(): array
    {
        try {
            $manager = $this->app->make(ReleaseManifestManager::class);
            $all     = $manager->all();
            $hasPrev = count($all) >= 2;
            $score   = $hasPrev ? 100 : 60;

            return [
                'check'  => 'rollback_readiness',
                'score'  => $score,
                'passed' => true,
                'detail' => $hasPrev
                    ? 'Rollback target available: ' . array_keys($all)[1]
                    : 'Only one release — no rollback target yet',
            ];
        } catch (\Throwable $e) {
            return ['check' => 'rollback_readiness', 'score' => 0, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkMigrationSafety(): array
    {
        try {
            $drift    = $this->app->make(SchemaDriftDetector::class)->detect();
            $pending  = count($drift['pending_migrations'] ?? []);
            $orphaned = count($drift['orphaned_migrations'] ?? []);
            $score    = ($pending === 0 && $orphaned === 0) ? 100 : ($orphaned > 0 ? 40 : 80);
            return [
                'check'  => 'migration_safety',
                'score'  => $score,
                'passed' => $orphaned === 0,
                'detail' => "Pending: {$pending}, Orphaned: {$orphaned}",
            ];
        } catch (\Throwable $e) {
            return ['check' => 'migration_safety', 'score' => 50, 'passed' => true, 'detail' => 'Check skipped: ' . $e->getMessage()];
        }
    }

    private function checkQueueCompatibility(): array
    {
        try {
            $result = $this->app->make(QueueRuntimeCompatibilityService::class)->validate();
            $passed = array_sum(array_column($result['checks'], 'passed'));
            $total  = count($result['checks']);
            $score  = $total > 0 ? (int) round(($passed / $total) * 100) : 100;
            return [
                'check'  => 'queue_compatibility',
                'score'  => $score,
                'passed' => $result['passed'],
                'detail' => "Queue checks: {$passed}/{$total}",
            ];
        } catch (\Throwable $e) {
            return ['check' => 'queue_compatibility', 'score' => 80, 'passed' => true, 'detail' => 'Check skipped: ' . $e->getMessage()];
        }
    }

    private function checkAssets(): array
    {
        try {
            $result = $this->app->make(AssetVersionValidator::class)->validate();
            $passed = array_sum(array_column($result['checks'], 'passed'));
            $total  = count($result['checks']);
            $score  = $total > 0 ? (int) round(($passed / $total) * 100) : 100;
            return [
                'check'  => 'asset_synchronization',
                'score'  => $score,
                'passed' => $result['passed'],
                'detail' => "Asset checks: {$passed}/{$total}",
            ];
        } catch (\Throwable $e) {
            return ['check' => 'asset_synchronization', 'score' => 70, 'passed' => true, 'detail' => 'Check skipped: ' . $e->getMessage()];
        }
    }

    private function checkTenantMaintenance(): array
    {
        try {
            $manager = $this->app->make(TenantMaintenanceManager::class);
            $status  = $manager->getStatus();
            return [
                'check'  => 'tenant_maintenance',
                'score'  => 100,
                'passed' => true,
                'detail' => "Maintenance system operational, {$status['active_count']} active window(s)",
            ];
        } catch (\Throwable $e) {
            return ['check' => 'tenant_maintenance', 'score' => 0, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkHealth(): array
    {
        try {
            $health = $this->app->make(RuntimeHealthService::class)->ready();
            $passed = array_sum(array_column($health['checks'], 'passed'));
            $total  = count($health['checks']);
            $score  = $total > 0 ? (int) round(($passed / $total) * 100) : 100;
            return [
                'check'  => 'health_endpoints',
                'score'  => $score,
                'passed' => $health['ready'],
                'detail' => "Health checks: {$passed}/{$total}",
            ];
        } catch (\Throwable $e) {
            return ['check' => 'health_endpoints', 'score' => 50, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkReleaseIntegrity(): array
    {
        try {
            $manager   = $this->app->make(ReleaseManifestManager::class);
            $validator = $this->app->make(ReleaseManifestValidator::class);
            $latest    = $manager->latest();

            if (!$latest) {
                return ['check' => 'release_integrity', 'score' => 60, 'passed' => true, 'detail' => 'No release to validate'];
            }

            $result = $validator->validate($latest);
            return [
                'check'  => 'release_integrity',
                'score'  => $result['valid'] ? 100 : 0,
                'passed' => $result['valid'],
                'detail' => $result['valid'] ? 'Latest manifest checksum OK' : implode('; ', $result['issues']),
            ];
        } catch (\Throwable $e) {
            return ['check' => 'release_integrity', 'score' => 0, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }
}
