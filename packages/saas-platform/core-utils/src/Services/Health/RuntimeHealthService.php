<?php

namespace SaaSPlatform\CoreUtils\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Migration\SchemaDriftDetector;

/**
 * Platform runtime health service.
 *
 * Provides three health gate levels:
 *  - live:   Is the process alive?
 *  - ready:  Can the process serve traffic?
 *  - release: Is the current release coherent?
 */
class RuntimeHealthService
{
    public function __construct(
        private ReleaseManifestManager $manifestManager,
        private SchemaDriftDetector    $driftDetector
    ) {}

    public function live(): array
    {
        return [
            'status'    => 'alive',
            'timestamp' => now()->toIso8601String(),
            'php'       => PHP_VERSION,
        ];
    }

    public function ready(): array
    {
        $checks = [];
        $checks['database'] = $this->checkDatabase();
        $checks['cache']    = $this->checkCache();
        $checks['storage']  = $this->checkStorage();
        $checks['queue']    = $this->checkQueue();

        $allPassed = !in_array(false, array_column($checks, 'passed'), true);

        return [
            'ready'     => $allPassed,
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ];
    }

    public function release(): array
    {
        $latest  = $this->manifestManager->latest();
        $drift   = $this->driftDetector->detect();
        $hasDrift = !empty($drift['pending_migrations']) || !empty($drift['orphaned_migrations']);

        return [
            'coherent'           => !$hasDrift && $latest !== null,
            'timestamp'          => now()->toIso8601String(),
            'latest_release'     => $latest ? [
                'uuid'       => $latest['deployment_uuid'],
                'released_at'=> $latest['released_at'],
                'git_commit' => $latest['git_commit'] ?? null,
                'platform'   => $latest['platform_version'] ?? null,
            ] : null,
            'schema_drift'       => $hasDrift,
            'pending_migrations' => count($drift['pending_migrations'] ?? []),
        ];
    }

    public function full(): array
    {
        return [
            'live'      => $this->live(),
            'ready'     => $this->ready(),
            'release'   => $this->release(),
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env', 'production'),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            $tables = DB::select('SHOW TABLES');
            return ['passed' => true, 'detail' => count($tables) . ' tables accessible'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = '__health_' . time();
            Cache::put($key, 1, 5);
            $ok = Cache::get($key) === 1;
            Cache::forget($key);
            return ['passed' => $ok, 'detail' => $ok ? 'Read/write OK' : 'Cache probe failed'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = '__health_probe_' . time();
            Storage::put($path, '1');
            $ok = Storage::exists($path);
            Storage::delete($path);
            return ['passed' => $ok, 'detail' => $ok ? 'Default disk writable' : 'Storage write failed'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default', 'sync');

        if ($driver === 'sync') {
            return ['passed' => true, 'detail' => 'sync driver'];
        }

        try {
            $failed = DB::table('failed_jobs')->count();
            return ['passed' => true, 'detail' => "driver={$driver}, failed={$failed}"];
        } catch (\Throwable $e) {
            return ['passed' => false, 'detail' => $e->getMessage()];
        }
    }
}
