<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * Phase 14-F3 — extracted verbatim from the pre-14-F3 ConfigureQueueStage.
 * Same Supervisor config, same skip-on-write-failure behavior, same
 * supervisorctl reread/update/status calls, same rollback. No behavior
 * change for VPS/SSH installations.
 */
class VpsQueueProvisioner implements QueueProvisionerInterface
{
    public function installWorker(InstallationContext $ctx): ProvisioningResult
    {
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
            return ProvisioningResult::skipped("Cannot write {$configPath} — manual Supervisor setup required");
        }

        $ctx->connection->execute('supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null || true');

        $statusResult = $ctx->connection->execute("supervisorctl status {$slug}_worker 2>/dev/null || echo 'pending'");

        return ProvisioningResult::installed(
            ['config_path' => $configPath, 'worker_status' => trim($statusResult->stdout)],
            ['config_path' => $configPath],
        );
    }

    public function removeWorker(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['config_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['config_path']));
            $ctx->connection->execute('supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null || true');
        }
    }
}
