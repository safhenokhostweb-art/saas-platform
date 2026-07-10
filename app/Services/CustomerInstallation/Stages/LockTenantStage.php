<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

/**
 * Phase 13-J — puts the tenant's own site into Laravel maintenance mode
 * before any mutating stage (upload/extract/migrate) runs, via the same
 * RemoteConnectionInterface every other stage already uses.
 *
 * This is the mechanism 13-A/13-B identified as missing: saas-platform's
 * own TenantMaintenanceManager only governs requests hitting saas-platform
 * itself, never the tenant's independently hosted server. Running
 * `artisan down` remotely, on the tenant's own codebase, is the only way
 * to actually lock that server.
 *
 * canRollback() is deliberately true with no meaningful pre-snapshot —
 * its rollback() is the safety net: if any later stage fails, the
 * existing LIFO rollback replay (CustomerInstallationRollbackService,
 * 13-I) runs `artisan up` automatically, so a failed upgrade never
 * leaves the tenant stuck in maintenance mode.
 */
class LockTenantStage extends AbstractStage
{
    public function name(): string { return 'LOCK_TENANT'; }
    public function label(): string { return 'قفل کردن سایت (حالت نگهداری)'; }
    public function estimatedSeconds(): int { return 5; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = escapeshellarg($ctx->webRoot());

            $result = $ctx->connection->execute("cd {$webRoot} && php artisan down --retry=60 2>&1");

            if ($result->failed()) {
                return StageResult::failure($this->name(), 'Failed to enable maintenance mode: ' . $result->output(), $this->elapsed());
            }

            return StageResult::success($this->name(), [
                'locked' => true,
            ], [], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $webRoot = escapeshellarg($ctx->webRoot());
        $ctx->connection->execute("cd {$webRoot} && php artisan up 2>&1");
    }
}
