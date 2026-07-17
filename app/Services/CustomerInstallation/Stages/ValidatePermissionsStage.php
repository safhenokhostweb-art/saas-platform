<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ValidatePermissionsStage extends AbstractStage
{
    public function name(): string { return 'VALIDATE_PERMISSIONS'; }
    public function label(): string { return 'اعتبارسنجی دسترسی‌ها'; }
    public function estimatedSeconds(): int { return 3; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $isVpsFamily = $ctx->connection->driverType()->isVpsFamily();
            $warnings    = [];

            // Phase 15-G — Part 4: /var/www is a VPS-only path (that's where
            // VpsRemoteDriver installs create /var/www/{slug}). A cPanel SSH
            // account's own home directory is a completely different tree
            // that a non-root account virtually never has write access to —
            // this check previously ran unconditionally and would hard-FAIL
            // every single cPanel installation at this stage, before
            // PROVISION_DOMAIN (or anything from Phase 15-D/15-E) was ever
            // reached. Discovered during this phase's driver-awareness pass,
            // not by the Phase 15-F audit. For a non-VPS-family driver, probe
            // a file relative to the SSH login's own home directory instead —
            // the one path guaranteed to be writable by that account.
            $testFile = $isVpsFamily
                ? '/var/www/.cie_perm_test_' . uniqid()
                : '.cie_perm_test_' . uniqid();

            $writeResult = $ctx->connection->execute("echo 'test' > " . escapeshellarg($testFile) . " 2>&1 && echo 'ok'");
            if (!str_contains($writeResult->stdout, 'ok')) {
                $location = $isVpsFamily ? '/var/www' : "the account's home directory";
                return StageResult::failure($this->name(), "{$location} is not writable by the deployment user", $this->elapsed());
            }

            $ctx->connection->execute("rm -f " . escapeshellarg($testFile));

            $supervisorWritable = true;
            $cronWritable       = true;

            // /etc/supervisor and /etc/cron.d are also VPS-only paths — a
            // cPanel account provisions queue/scheduler via UAPI Cron
            // (CpanelQueueProvisioner/CpanelCronProvisioner), never these
            // filesystem locations, so checking them there produced a false
            // WARN on every cPanel install.
            if ($isVpsFamily) {
                $supResult = $ctx->connection->execute('test -w /etc/supervisor/conf.d/ 2>/dev/null && echo ok || echo no');
                $supervisorWritable = str_contains($supResult->stdout, 'ok');
                if (!$supervisorWritable) {
                    $warnings[] = '/etc/supervisor/conf.d/ not writable — queue worker auto-config skipped';
                }

                $cronResult = $ctx->connection->execute('test -w /etc/cron.d/ 2>/dev/null && echo ok || echo no');
                $cronWritable = str_contains($cronResult->stdout, 'ok');
                if (!$cronWritable) {
                    $warnings[] = '/etc/cron.d/ not writable — scheduler auto-config skipped';
                }
            }

            return StageResult::success($this->name(), [
                'web_root_writable'   => true,
                'supervisor_writable' => $supervisorWritable,
                'cron_writable'       => $cronWritable,
                'warnings'            => $warnings,
            ], [], $this->elapsed());
        });
    }
}
