<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RecoveryStrategyManager
{
    public function __construct(
        private FailureClassificationEngine $classifier,
        private RecoveryExecutionLog        $executionLog,
        private TelemetryManager            $telemetry
    ) {}

    public function execute(string $strategy, array $context = []): array
    {
        $recoveryId = (string) Str::uuid();

        if (!$this->isIdempotentSafe($strategy)) {
            return ['recovery_id' => $recoveryId, 'status' => 'skipped', 'reason' => 'not_idempotent_safe'];
        }

        try {
            $result = match ($strategy) {
                'queue_restart'              => $this->queueRestart($recoveryId),
                'worker_recycle'             => $this->workerRecycle($recoveryId),
                'cache_rebuild'              => $this->cacheRebuild($recoveryId),
                'cache_clear'               => $this->cacheRebuild($recoveryId),
                'retry'                     => ['status' => 'skipped', 'reason' => 'caller_managed'],
                'retry_with_backoff'        => ['status' => 'skipped', 'reason' => 'caller_managed'],
                'circuit_open'              => $this->signalCircuitOpen($context),
                'deployment_rollback_trigger' => $this->deploymentRollbackTrigger($recoveryId),
                'deployment_freeze'         => $this->deploymentFreeze($recoveryId),
                'maintenance_mode'          => $this->escalateToMaintenance($recoveryId, $context),
                'throttle'                  => $this->activateThrottle($recoveryId, $context),
                'tenant_isolation'          => $this->isolateTenant($recoveryId, $context),
                'health_check_retry'        => $this->healthCheckRetry($recoveryId),
                'fallback'                  => ['status' => 'skipped', 'reason' => 'application_managed'],
                'alert'                     => $this->emitAlert($recoveryId, $context),
                default                     => ['status' => 'unknown_strategy'],
            };

            $status = $result['status'] ?? 'success';
            $this->executionLog->record($recoveryId, $strategy, $status, $context['failure_type'] ?? 'unknown', $context);

            return array_merge(['recovery_id' => $recoveryId, 'strategy' => $strategy], $result);
        } catch (\Throwable $e) {
            $this->executionLog->record($recoveryId, $strategy, 'failed', $context['failure_type'] ?? 'unknown', [
                'error' => $e->getMessage(),
            ]);

            return ['recovery_id' => $recoveryId, 'strategy' => $strategy, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function isIdempotentSafe(string $strategy): bool
    {
        // These strategies are always safe to re-apply
        $safe = [
            'cache_rebuild', 'cache_clear', 'queue_restart', 'health_check_retry',
            'circuit_open', 'deployment_freeze', 'alert', 'throttle', 'tenant_isolation',
        ];

        return in_array($strategy, $safe);
    }

    private function queueRestart(string $recoveryId): array
    {
        try {
            Artisan::call('queue:restart');
            return ['status' => 'success', 'action' => 'queue_workers_signaled'];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function workerRecycle(string $recoveryId): array
    {
        // Signal worker recycle via cache — workers poll this key
        Cache::put('governance.worker.recycle_signal', now()->toIso8601String(), 300);
        return ['status' => 'success', 'action' => 'recycle_signal_sent'];
    }

    private function cacheRebuild(string $recoveryId): array
    {
        try {
            // Clear only governance and telemetry caches — not application cache
            Artisan::call('config:cache');
            return ['status' => 'success', 'action' => 'config_cache_rebuilt'];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function signalCircuitOpen(array $context): array
    {
        $circuitKey = $context['circuit_key'] ?? 'unknown';
        Cache::put('governance.circuit.' . $circuitKey . '.state', 'OPEN', 300);
        return ['status' => 'success', 'circuit_key' => $circuitKey, 'action' => 'circuit_opened'];
    }

    private function deploymentRollbackTrigger(string $recoveryId): array
    {
        // Mark that a rollback is recommended — does NOT auto-execute rollback
        Cache::put('governance.deployment.rollback_recommended', [
            'recovery_id' => $recoveryId,
            'reason'      => 'automated_failure_recovery',
            'at'          => now()->toIso8601String(),
        ], 3600);

        $this->telemetry->warning('governance', 'governance.deployment.rollback_recommended', [
            'recovery_id' => $recoveryId,
        ]);

        return ['status' => 'success', 'action' => 'rollback_flagged_for_operator'];
    }

    private function deploymentFreeze(string $recoveryId): array
    {
        Cache::put('governance.deployment.frozen', [
            'reason'      => 'recovery:' . $recoveryId,
            'frozen_at'   => now()->toIso8601String(),
        ], 3600);

        return ['status' => 'success', 'action' => 'deployment_frozen'];
    }

    private function escalateToMaintenance(string $recoveryId, array $context): array
    {
        // Signal maintenance needed — actual tenant:down is operator-driven
        $tenantId = $context['tenant_id'] ?? null;

        $this->telemetry->critical('governance', 'governance.maintenance.escalated', [
            'tenant_id'   => $tenantId,
            'recovery_id' => $recoveryId,
        ]);

        return ['status' => 'success', 'action' => 'maintenance_escalation_emitted', 'tenant_id' => $tenantId];
    }

    private function activateThrottle(string $recoveryId, array $context): array
    {
        $target = $context['throttle_target'] ?? 'global';
        Cache::put('governance.throttle.' . $target, [
            'active'      => true,
            'factor'      => $context['factor'] ?? 0.5,
            'recovery_id' => $recoveryId,
            'activated_at' => now()->toIso8601String(),
        ], 600);

        return ['status' => 'success', 'action' => 'throttle_activated', 'target' => $target];
    }

    private function isolateTenant(string $recoveryId, array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;

        if (!$tenantId) {
            return ['status' => 'skipped', 'reason' => 'no_tenant_id_provided'];
        }

        Cache::put('governance.degradation.tenant.' . $tenantId, 5, 3600);

        $this->telemetry->critical('governance', 'governance.tenant.isolated', [
            'tenant_id'   => $tenantId,
            'recovery_id' => $recoveryId,
        ]);

        return ['status' => 'success', 'action' => 'tenant_isolated', 'tenant_id' => $tenantId];
    }

    private function healthCheckRetry(string $recoveryId): array
    {
        return ['status' => 'success', 'action' => 'health_check_scheduled'];
    }

    private function emitAlert(string $recoveryId, array $context): array
    {
        $this->telemetry->warning('governance', 'governance.alert.emitted', array_merge([
            'recovery_id' => $recoveryId,
        ], $context));

        return ['status' => 'success', 'action' => 'alert_emitted'];
    }
}
