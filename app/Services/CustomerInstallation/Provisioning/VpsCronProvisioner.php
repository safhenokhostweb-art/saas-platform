<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * Phase 14-F3 — extracted verbatim from the pre-14-F3 ConfigureSchedulerStage.
 * Same /etc/cron.d write, same skip-on-write-failure behavior, same rollback.
 * No behavior change for VPS/SSH installations.
 */
class VpsCronProvisioner implements CronProvisionerInterface
{
    public function installScheduler(InstallationContext $ctx): ProvisioningResult
    {
        $slug = $ctx->customerSlug();
        $webRoot = $ctx->webRoot();
        $cronPath = "/etc/cron.d/{$slug}";

        $cronEntry = "* * * * * www-data cd {$webRoot} && php artisan schedule:run >> /dev/null 2>&1\n";
        $encoded = base64_encode($cronEntry);

        $writeResult = $ctx->connection->execute(
            "echo " . escapeshellarg($encoded) . " | base64 -d > " . escapeshellarg($cronPath)
            . " 2>&1 && chmod 644 " . escapeshellarg($cronPath) . " && echo 'ok'"
        );

        if (!str_contains($writeResult->stdout, 'ok')) {
            return ProvisioningResult::skipped("Cannot write {$cronPath} — manual cron setup required");
        }

        return ProvisioningResult::installed(
            ['cron_path' => $cronPath, 'cron_entry' => $cronEntry],
            ['cron_path' => $cronPath],
        );
    }

    public function removeScheduler(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['cron_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['cron_path']));
        }
    }
}
