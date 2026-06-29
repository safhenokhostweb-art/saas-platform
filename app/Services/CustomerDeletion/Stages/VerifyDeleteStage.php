<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\CustomerInstallation;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 14: VERIFY_DELETE
 *
 * Comprehensive final verification that nothing remains. Checks every
 * artifact that should have been removed. Stores a full verification
 * report on the CustomerDeletion record.
 *
 * Each item is independently checked so partial failures are surfaced
 * clearly. The overall result is PASS only if every critical check passes.
 */
class VerifyDeleteStage extends AbstractDeleteStage
{
    public function name(): string { return 'VERIFY_DELETE'; }
    public function label(): string { return 'تأیید نهایی حذف'; }
    public function isCritical(): bool { return true; }
    public function estimatedSeconds(): int { return 15; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $report = $this->runAllChecks($ctx);

            // Store full verification report
            $ctx->deletion->update(['verify_report' => $report]);

            $failures = array_filter($report['checks'], fn($c) => !$c['passed'] && $c['critical']);

            if (!empty($failures)) {
                $failNames = implode(', ', array_column(array_values($failures), 'check'));
                return DeleteStageResult::failure(
                    $this->name(),
                    "تأیید ناموفق: {$failNames}",
                    $this->elapsed()
                );
            }

            return DeleteStageResult::success($this->name(), $report);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $report = $ctx->deletion->verify_report ?? [];
        return $report['checks'] ?? [];
    }

    private function runAllChecks(DeleteContext $ctx): array
    {
        $checks = [];

        // ── Remote server checks ─────────────────────────────────────────────
        $checks[] = [
            'check'    => 'Web root removed',
            'passed'   => !$this->remoteExists($ctx, $ctx->webRoot),
            'detail'   => $ctx->webRoot,
            'critical' => true,
        ];

        $checks[] = [
            'check'    => 'Nginx config removed',
            'passed'   => !$this->remoteExists($ctx, $ctx->nginxConfigPath),
            'detail'   => $ctx->nginxConfigPath,
            'critical' => true,
        ];

        $checks[] = [
            'check'    => 'Nginx symlink removed',
            'passed'   => !$this->remoteExists($ctx, $ctx->nginxEnabledPath),
            'detail'   => $ctx->nginxEnabledPath,
            'critical' => false,
        ];

        $checks[] = [
            'check'    => 'Supervisor config removed',
            'passed'   => !$this->remoteExists($ctx, $ctx->supervisorConfigPath),
            'detail'   => $ctx->supervisorConfigPath,
            'critical' => false,
        ];

        $checks[] = [
            'check'    => 'Supervisor worker stopped',
            'passed'   => !$this->supervisorProgramRunning($ctx, $ctx->supervisorProgramName),
            'detail'   => $ctx->supervisorProgramName,
            'critical' => false,
        ];

        $checks[] = [
            'check'    => 'Cron entry removed',
            'passed'   => !$this->remoteExists($ctx, $ctx->cronPath),
            'detail'   => $ctx->cronPath,
            'critical' => false,
        ];

        // ── Database checks ──────────────────────────────────────────────────
        if ($ctx->dbName) {
            $dbOut = $this->remoteExec($ctx,
                'mysql -u root -e ' . escapeshellarg("SHOW DATABASES LIKE '{$ctx->dbName}';") . ' 2>/dev/null | wc -l'
            );
            $checks[] = [
                'check'    => "Database {$ctx->dbName} dropped",
                'passed'   => (int) trim($dbOut) <= 1,
                'detail'   => "db_name={$ctx->dbName}",
                'critical' => true,
            ];
        }

        if ($ctx->dbUser) {
            $userOut = $this->remoteExec($ctx,
                'mysql -u root -e ' . escapeshellarg("SELECT User FROM mysql.user WHERE User='{$ctx->dbUser}';") . ' 2>/dev/null | wc -l'
            );
            $checks[] = [
                'check'    => "DB user {$ctx->dbUser} dropped",
                'passed'   => (int) trim($userOut) <= 1,
                'detail'   => "db_user={$ctx->dbUser}",
                'critical' => false,
            ];
        }

        // ── Platform DB checks ───────────────────────────────────────────────
        if ($ctx->siteId) {
            $siteGone = Site::find($ctx->siteId) === null;
            $checks[] = [
                'check'    => "Site #{$ctx->siteId} deleted from platform DB",
                'passed'   => $siteGone,
                'detail'   => "site_id={$ctx->siteId}",
                'critical' => true,
            ];
        }

        if ($ctx->licenseKey) {
            $licenseGone = !PlatformLicense::where('license_key', $ctx->licenseKey)->exists();
            $checks[] = [
                'check'    => 'PlatformLicense record deleted',
                'passed'   => $licenseGone,
                'detail'   => substr($ctx->licenseKey, 0, 8) . '...',
                'critical' => true,
            ];
        }

        $installStatus = CustomerInstallation::find($ctx->installation->id)?->status;
        $checks[] = [
            'check'    => 'Installation marked deleted',
            'passed'   => $installStatus === 'deleted',
            'detail'   => "status={$installStatus}",
            'critical' => false,
        ];

        // ── Temp file checks ─────────────────────────────────────────────────
        $tmpArtifacts = $this->remoteExec($ctx,
            "ls /tmp/{$ctx->slug}_* 2>/dev/null | wc -l"
        );
        $checks[] = [
            'check'    => 'Temporary files cleaned',
            'passed'   => (int) trim($tmpArtifacts) === 0,
            'detail'   => trim($tmpArtifacts) . ' tmp files remaining',
            'critical' => false,
        ];

        $total   = count($checks);
        $passed  = count(array_filter($checks, fn($c) => $c['passed']));
        $failed  = $total - $passed;
        $score   = $total > 0 ? round($passed / $total * 100) : 100;

        return [
            'checks'         => $checks,
            'total'          => $total,
            'passed'         => $passed,
            'failed'         => $failed,
            'score'          => $score,
            'verdict'        => $failed === 0 ? 'PASS' : ($score >= 80 ? 'PARTIAL' : 'FAIL'),
            'verified_at'    => now()->toIso8601String(),
        ];
    }
}
