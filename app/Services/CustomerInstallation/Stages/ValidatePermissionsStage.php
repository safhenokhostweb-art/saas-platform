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
            $testFile = '/var/www/.cie_perm_test_' . uniqid();
            $warnings = [];

            $writeResult = $ctx->connection->execute("echo 'test' > " . escapeshellarg($testFile) . " 2>&1 && echo 'ok'");
            if (!str_contains($writeResult->stdout, 'ok')) {
                return StageResult::failure($this->name(), '/var/www is not writable by deployment user', $this->elapsed());
            }

            $ctx->connection->execute("rm -f " . escapeshellarg($testFile));

            $supResult = $ctx->connection->execute('test -w /etc/supervisor/conf.d/ 2>/dev/null && echo ok || echo no');
            if (!str_contains($supResult->stdout, 'ok')) {
                $warnings[] = '/etc/supervisor/conf.d/ not writable — queue worker auto-config skipped';
            }

            $cronResult = $ctx->connection->execute('test -w /etc/cron.d/ 2>/dev/null && echo ok || echo no');
            if (!str_contains($cronResult->stdout, 'ok')) {
                $warnings[] = '/etc/cron.d/ not writable — scheduler auto-config skipped';
            }

            return StageResult::success($this->name(), [
                'web_root_writable'  => true,
                'supervisor_writable'=> empty(array_filter($warnings, fn($w) => str_contains($w, 'supervisor'))),
                'cron_writable'      => empty(array_filter($warnings, fn($w) => str_contains($w, 'cron'))),
                'warnings'           => $warnings,
            ], [], $this->elapsed());
        });
    }
}
