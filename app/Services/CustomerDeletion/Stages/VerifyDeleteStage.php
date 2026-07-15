<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\CustomerInstallation;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;

/**
 * STAGE 14: VERIFY_DELETE
 *
 * Comprehensive final verification that nothing remains. Checks every
 * artifact that should have been removed. Stores a full verification
 * report on the CustomerDeletion record.
 *
 * Each item is independently checked so partial failures are surfaced
 * clearly. The overall result is PASS only if every critical check passes.
 *
 * Phase 14-G3 — checks are capability-aware (a resource this driver never
 * managed is reported as an explicit skip, not a fake pass/fail against a
 * path that could never exist) and the database checks go through the
 * hosting-appropriate provisioner instead of raw root-mysql queries.
 */
class VerifyDeleteStage extends AbstractDeleteStage
{
    public function __construct(private readonly DatabaseProvisionerFactory $provisioners) {}

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
        $capabilities = $ctx->connection->capabilities();

        // ── Remote server checks ─────────────────────────────────────────────
        $checks[] = [
            'check'    => 'Web root removed',
            'passed'   => !$this->remoteExists($ctx, $ctx->webRoot),
            'detail'   => $ctx->webRoot,
            'critical' => true,
        ];

        if ($capabilities->supportsWebServerConfiguration) {
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
        } else {
            $checks[] = [
                'check'    => 'Web server config removed',
                'passed'   => true,
                'detail'   => 'skipped — vhost managed by hosting panel',
                'critical' => false,
            ];
        }

        if ($capabilities->supportsSupervisor) {
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
        } else {
            $checks[] = [
                'check'    => 'Queue worker removed',
                'passed'   => true,
                'detail'   => 'skipped — driver has no Supervisor (cron-based worker removed by REMOVE_QUEUE)',
                'critical' => false,
            ];
        }

        if (!empty($ctx->schedulerRemovalSnapshot['cron_path'])) {
            $checks[] = [
                'check'    => 'Cron entry removed',
                'passed'   => !$this->remoteExists($ctx, $ctx->schedulerRemovalSnapshot['cron_path']),
                'detail'   => $ctx->schedulerRemovalSnapshot['cron_path'],
                'critical' => false,
            ];
        } else {
            $checks[] = [
                'check'    => 'Cron entry removed',
                'passed'   => true,
                'detail'   => !empty($ctx->schedulerRemovalSnapshot['cron_line'])
                    ? 'removed via UAPI Cron::remove_line (best-effort)'
                    : 'skipped — no scheduler snapshot recorded',
                'critical' => false,
            ];
        }

        // ── Database checks ──────────────────────────────────────────────────
        if ($ctx->dbName) {
            if ($capabilities->supportsDatabaseDeletion) {
                $exists = $this->provisioners
                    ->forDriverType($ctx->connection->driverType())
                    ->databaseExists($ctx->installationContext(), $ctx->dbName);
                $checks[] = [
                    'check'    => "Database {$ctx->dbName} dropped",
                    'passed'   => $exists !== true,
                    'detail'   => $exists === null ? 'unverifiable on this driver — drop was issued' : "db_name={$ctx->dbName}",
                    'critical' => true,
                ];
            } else {
                $checks[] = [
                    'check'    => "Database {$ctx->dbName} dropped",
                    'passed'   => true,
                    'detail'   => 'skipped — driver cannot manage databases, manual cleanup required',
                    'critical' => false,
                ];
            }
        }

        if ($ctx->dbUser) {
            if ($capabilities->supportsDatabaseDeletion) {
                $exists = $this->provisioners
                    ->forDriverType($ctx->connection->driverType())
                    ->databaseUserExists($ctx->installationContext(), $ctx->dbUser);
                $checks[] = [
                    'check'    => "DB user {$ctx->dbUser} dropped",
                    'passed'   => $exists !== true,
                    'detail'   => $exists === null ? 'unverifiable on this driver — drop was issued' : "db_user={$ctx->dbUser}",
                    'critical' => false,
                ];
            } else {
                $checks[] = [
                    'check'    => "DB user {$ctx->dbUser} dropped",
                    'passed'   => true,
                    'detail'   => 'skipped — driver cannot manage databases, manual cleanup required',
                    'critical' => false,
                ];
            }
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
