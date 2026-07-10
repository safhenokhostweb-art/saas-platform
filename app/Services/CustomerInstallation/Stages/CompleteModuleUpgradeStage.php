<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\PostInstallValidationService;

/**
 * Phase 13-I — health check + completion marker for a module upgrade.
 *
 * Reuses PostInstallValidationService::runAll() unmodified — the same
 * checks CustomerReadyStage runs for fresh installs — but does not touch
 * storage/installed.lock or the .cie_installing sentinel: those are
 * whole-site, install-time-only concerns. The site was already live and
 * serving requests before this upgrade began.
 */
class CompleteModuleUpgradeStage extends AbstractStage
{
    public function __construct(
        private readonly InstallationStateInterface $state,
        private readonly PostInstallValidationService $validator,
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
}
