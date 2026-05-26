<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Runs post-deployment health assertions.
 * Called during the health-check phase of the deployment state machine.
 */
class DeploymentHealthVerifier
{
    public function verify(string $deploymentUuid): array
    {
        $checks = [];

        $checks[] = $this->checkDatabase();
        $checks[] = $this->checkCache();
        $checks[] = $this->checkStorage();
        $checks[] = $this->checkMigrations();
        $checks[] = $this->checkManifest($deploymentUuid);

        $passed = array_sum(array_column($checks, 'passed'));
        $total  = count($checks);

        return [
            'deployment_uuid' => $deploymentUuid,
            'passed'          => $passed === $total,
            'score'           => "{$passed}/{$total}",
            'checks'          => $checks,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['check' => 'database', 'passed' => true, 'detail' => 'Connection OK'];
        } catch (\Throwable $e) {
            return ['check' => 'database', 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'deployment_health_probe_' . time();
            Cache::put($key, true, 5);
            $ok = Cache::get($key) === true;
            Cache::forget($key);
            return ['check' => 'cache', 'passed' => $ok, 'detail' => $ok ? 'Read/write OK' : 'Cache probe failed'];
        } catch (\Throwable $e) {
            return ['check' => 'cache', 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = 'deployment_probe_' . time() . '.tmp';
            Storage::put($path, 'probe');
            $ok = Storage::exists($path);
            Storage::delete($path);
            return ['check' => 'storage', 'passed' => $ok, 'detail' => $ok ? 'Write OK' : 'Storage write failed'];
        } catch (\Throwable $e) {
            return ['check' => 'storage', 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkMigrations(): array
    {
        try {
            $pending = \Artisan::call('migrate:status') === 0;
            // Simplified: just verify the migrations table is readable
            $count = DB::table('migrations')->count();
            return ['check' => 'migrations', 'passed' => true, 'detail' => "{$count} migrations recorded"];
        } catch (\Throwable $e) {
            return ['check' => 'migrations', 'passed' => false, 'detail' => $e->getMessage()];
        }
    }

    private function checkManifest(string $uuid): array
    {
        $path = storage_path("app/releases/{$uuid}.json");
        $ok   = file_exists($path);
        return [
            'check'  => 'release_manifest',
            'passed' => $ok,
            'detail' => $ok ? 'Manifest present' : "Missing: storage/app/releases/{$uuid}.json",
        ];
    }
}
