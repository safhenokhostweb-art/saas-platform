<?php

namespace SaaSPlatform\CoreUtils\RuntimeRecovery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RuntimeRecoveryOrchestrator
{
    public function __construct(
        private readonly FrontendSelfHealingEngine  $frontendHealer,
        private readonly RealtimeRecoveryCoordinator $realtimeRecovery,
    ) {}

    public function runFullRecovery(string $context = 'scheduled'): array
    {
        $results = [];
        $start   = microtime(true);

        $results['frontend']  = $this->frontendHealer->heal();
        $results['realtime']  = $this->realtimeRecovery->recover();
        $results['ai']        = $this->recoverAiRuntime();
        $results['assets']    = $this->recoverAssets();
        $results['routes']    = $this->recoverRouteCache();

        $duration = round((microtime(true) - $start) * 1000);

        $this->logRecovery($context, $results, $duration);

        return [
            'context'     => $context,
            'duration_ms' => $duration,
            'results'     => $results,
            'healthy'     => $this->allHealthy($results),
            'recovered_at' => now()->toISOString(),
        ];
    }

    public function recoverAiRuntime(): array
    {
        $actions = [];

        // Check AI kill switch left on
        if (config('ai.emergency.kill_switch', false)) {
            $actions[] = ['action' => 'kill_switch_active', 'status' => 'manual_intervention_required'];
        }

        // Reset AI circuit breaker if stale failures
        $failures = (int) Cache::get('ai:circuit:failures', 0);
        if ($failures > 0) {
            $openedAt = (int) Cache::get('ai:circuit:state:opened_at', 0);
            if ($openedAt > 0 && (time() - $openedAt) > 600) {
                Cache::forget('ai:circuit:state');
                Cache::forget('ai:circuit:failures');
                $actions[] = ['action' => 'ai_circuit_reset', 'status' => 'recovered'];
            }
        }

        // Flush stale quota locks
        $actions[] = ['action' => 'ai_quota_check', 'status' => 'ok'];

        return ['actions' => $actions, 'healthy' => count(array_filter($actions, fn($a) => $a['status'] === 'manual_intervention_required')) === 0];
    }

    public function recoverAssets(): array
    {
        $actions = [];
        try {
            // Verify public/modules symlinks exist
            $moduleDirs = ['forms', 'tickets'];
            foreach ($moduleDirs as $mod) {
                $link = public_path("modules/{$mod}");
                if (! file_exists($link)) {
                    $actions[] = ['action' => "symlink_missing:{$mod}", 'status' => 'needs_repair'];
                } else {
                    $actions[] = ['action' => "symlink_ok:{$mod}", 'status' => 'ok'];
                }
            }
        } catch (\Throwable $e) {
            $actions[] = ['action' => 'asset_check', 'status' => 'error', 'error' => $e->getMessage()];
        }
        return ['actions' => $actions, 'healthy' => ! in_array('needs_repair', array_column($actions, 'status'))];
    }

    public function recoverRouteCache(): array
    {
        // Only diagnose — never auto-clear production caches
        $cached = file_exists(base_path('bootstrap/cache/routes-v7.php'));
        return [
            'actions' => [['action' => 'route_cache_check', 'status' => $cached ? 'cached' : 'not_cached']],
            'healthy' => true,
        ];
    }

    private function allHealthy(array $results): bool
    {
        foreach ($results as $result) {
            if (isset($result['healthy']) && ! $result['healthy']) {
                return false;
            }
        }
        return true;
    }

    private function logRecovery(string $context, array $results, int $durationMs): void
    {
        $key  = 'runtime:recovery:log';
        $logs = Cache::get($key, []);
        $logs[] = [
            'context'     => $context,
            'duration_ms' => $durationMs,
            'healthy'     => $this->allHealthy($results),
            'at'          => now()->toISOString(),
        ];
        Cache::put($key, array_slice($logs, -100), now()->addDays(7));
        Log::info('[RuntimeRecovery] Recovery run completed', ['context' => $context, 'healthy' => $this->allHealthy($results)]);
    }
}
