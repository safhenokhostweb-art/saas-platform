<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\PostInstallValidationService;
use App\Services\ModuleUpdateEligibilityService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 13-I — health check + completion marker for a module upgrade.
 *
 * Reuses PostInstallValidationService::runAll() unmodified — the same
 * checks CustomerReadyStage runs for fresh installs — but does not touch
 * storage/installed.lock or the .cie_installing sentinel: those are
 * whole-site, install-time-only concerns. The site was already live and
 * serving requests before this upgrade began.
 *
 * Phase 13-M4 — after a successful health check, also writes an optimistic
 * site_modules.installed_version so the platform UI reflects the upgrade
 * immediately rather than waiting for the tenant's next heartbeat. This is
 * a projection, not the deployment itself: see syncInstalledVersion() —
 * any failure there is logged and swallowed, never fails this stage.
 */
class CompleteModuleUpgradeStage extends AbstractStage
{
    public function __construct(
        private readonly InstallationStateInterface $state,
        private readonly PostInstallValidationService $validator,
        private readonly ModuleUpdateEligibilityService $eligibilityService,
    ) {}

    public function name(): string { return 'COMPLETE_MODULE_UPGRADE'; }
    public function label(): string { return 'بررسی سلامت و تکمیل بروزرسانی'; }
    public function estimatedSeconds(): int { return 20; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $checks = $this->validator->runAll($ctx);
            $overallStatus = $this->validator->overallStatus($checks);
            $requiredPass = $this->validator->allRequiredPass($checks);

            $ctx->installation->update([
                'post_install_validation' => [
                    'status' => $overallStatus,
                    'checks' => $checks,
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);

            if (!$requiredPass) {
                $failed = array_keys(array_filter($checks, fn ($c) => $c['required'] && $c['status'] !== 'PASS'));
                return StageResult::failure(
                    $this->name(),
                    'Post-upgrade health check: required checks FAILED — ' . implode(', ', $failed),
                    $this->elapsed()
                );
            }

            $this->state->markComplete($ctx->installation);
            $this->syncInstalledVersion($ctx);

            $warnings = array_keys(array_filter($checks, fn ($c) => $c['status'] === 'WARN'));

            return StageResult::success($this->name(), [
                'validation_status' => $overallStatus,
                'checks' => $checks,
                'warnings' => $warnings,
                'module_slug' => $ctx->moduleTarget?->moduleSlug,
                'target_version' => $ctx->moduleTarget?->targetVersion,
            ], [], $this->elapsed());
        });
    }

    /**
     * Phase 13-M4 — best-effort optimistic version sync. Reads directly
     * from the installation's own metadata (not $ctx->moduleTarget) so a
     * malformed/missing DTO can never be in the way — a missing or
     * incomplete metadata key here just means "skip and log," never a
     * stage failure. Version sync is a projection of a deployment that
     * already succeeded, not the deployment itself.
     */
    private function syncInstalledVersion(InstallationContext $ctx): void
    {
        if ($ctx->installation->install_mode !== 'MODE_MODULE_UPDATE') {
            return;
        }

        $siteId = $ctx->installation->site_id;
        $metadata = $ctx->installation->metadata ?? [];
        $moduleSlug = $metadata['module_slug'] ?? null;
        $targetVersion = $metadata['target_version'] ?? null;

        if (!$siteId || !$moduleSlug || !$targetVersion) {
            Log::warning('CIE COMPLETE_MODULE_UPGRADE: skipping version sync — incomplete metadata', [
                'installation_id' => $ctx->installation->id,
                'site_id' => $siteId,
                'module_slug' => $moduleSlug,
                'target_version' => $targetVersion,
            ]);
            return;
        }

        try {
            $updated = $this->eligibilityService->recordPipelineAssertedVersion((int) $siteId, $moduleSlug, $targetVersion);

            if (!$updated) {
                Log::warning('CIE COMPLETE_MODULE_UPGRADE: version sync matched no site_modules row', [
                    'installation_id' => $ctx->installation->id,
                    'site_id' => $siteId,
                    'module_slug' => $moduleSlug,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CIE COMPLETE_MODULE_UPGRADE: version sync failed', [
                'installation_id' => $ctx->installation->id,
                'site_id' => $siteId,
                'module_slug' => $moduleSlug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
