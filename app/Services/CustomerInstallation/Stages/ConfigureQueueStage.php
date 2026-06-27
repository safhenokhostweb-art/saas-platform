<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ConfigureQueueStage extends AbstractStage
{
    public function name(): string { return 'CONFIGURE_QUEUE'; }
    public function label(): string { return 'پیکربندی صف پردازش'; }
    public function estimatedSeconds(): int { return 8; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $slug = $ctx->customerSlug();
            $webRoot = $ctx->webRoot();
            $configPath = "/etc/supervisor/conf.d/{$slug}_worker.conf";
            $logPath = "/var/log/supervisor/{$slug}_worker.log";

            $config = "[program:{$slug}_worker]\n"
                . "command=php {$webRoot}/artisan queue:work redis --sleep=3 --tries=3 --timeout=300\n"
                . "autostart=true\n"
                . "autorestart=true\n"
                . "user=www-data\n"
                . "numprocs=1\n"
                . "redirect_stderr=true\n"
                . "stdout_logfile={$logPath}\n";

            $encoded = base64_encode($config);
            $writeResult = $ctx->connection->execute(
                "echo " . escapeshellarg($encoded) . " | base64 -d > " . escapeshellarg($configPath) . " 2>&1 && echo 'ok'"
            );

            if (!str_contains($writeResult->stdout, 'ok')) {
                // Supervisor not writable — warn, don't fail
                return StageResult::success($this->name(), [
                    'status'  => 'skipped',
                    'message' => "Cannot write {$configPath} — manual Supervisor setup required",
                ], [], $this->elapsed());
            }

            $ctx->connection->execute('supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null || true');

            $statusResult = $ctx->connection->execute("supervisorctl status {$slug}_worker 2>/dev/null || echo 'pending'");

            return StageResult::success($this->name(), [
                'config_path'   => $configPath,
                'worker_status' => trim($statusResult->stdout),
            ], ['config_path' => $configPath], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['config_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['config_path']));
            $ctx->connection->execute('supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null || true');
        }
    }
}
