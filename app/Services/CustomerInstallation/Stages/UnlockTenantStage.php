<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

/**
 * Phase 13-J — restores normal access after a successful module upgrade.
 *
 * Placed as the last stage in the MODE_MODULE_UPDATE list, after
 * CompleteModuleUpgradeStage — the orchestrator's run() loop stops on
 * the first failed stage, so this only ever executes once the health
 * check has already passed. A failed health check leaves the tenant
 * safely locked rather than exposing a broken site to live traffic.
 *
 * No canRollback() override needed: if this stage itself fails, the
 * pipeline's normal failure path still triggers LockTenantStage's own
 * rollback() (an unlock), so the tenant doesn't stay locked either way.
 */
class UnlockTenantStage extends AbstractStage
{
    public function name(): string { return 'UNLOCK_TENANT'; }
    public function label(): string { return 'باز کردن قفل سایت'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = escapeshellarg($ctx->webRoot());

            $result = $ctx->connection->execute("cd {$webRoot} && php artisan up 2>&1");

            if ($result->failed()) {
                return StageResult::failure($this->name(), 'Failed to disable maintenance mode: ' . $result->output(), $this->elapsed());
            }

            return StageResult::success($this->name(), [
                'locked' => false,
            ], [], $this->elapsed());
        });
    }
}
