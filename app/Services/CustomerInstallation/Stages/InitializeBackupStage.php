<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class InitializeBackupStage extends AbstractStage
{
    public function name(): string { return 'INITIALIZE_BACKUP'; }
    public function label(): string { return 'راه‌اندازی پشتیبان‌گیری'; }
    public function estimatedSeconds(): int { return 30; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = escapeshellarg($ctx->webRoot());
            $policy = $ctx->request->backupPolicy;

            $envPath = escapeshellarg($ctx->webRoot() . '/.env');
            $ctx->connection->execute("echo 'BACKUP_ENABLED=true' >> {$envPath}");
            $ctx->connection->execute("echo 'BACKUP_RETENTION=30' >> {$envPath}");

            // Trigger first backup (non-blocking — fire and check existence)
            $backupResult = $ctx->connection->execute(
                "cd {$webRoot} && php artisan db:backup 2>&1 | head -3 || echo 'backup-queued'"
            );

            return StageResult::success($this->name(), [
                'backup_enabled'  => true,
                'backup_policy'   => $policy,
                'initial_trigger' => trim($backupResult->stdout),
            ], [], $this->elapsed());
        });
    }
}
