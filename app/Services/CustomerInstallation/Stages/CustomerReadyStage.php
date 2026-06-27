<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\PostInstallValidationService;
use App\Services\Infrastructure\SlackAlertService;

class CustomerReadyStage extends AbstractStage
{
    public function __construct(
        private readonly InstallationStateInterface   $state,
        private readonly PostInstallValidationService $validator,
        private readonly SlackAlertService            $slack,
    ) {}

    public function name(): string { return 'CUSTOMER_READY'; }
    public function label(): string { return 'آماده‌سازی نهایی و تأیید'; }
    public function estimatedSeconds(): int { return 25; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;

            // Run all post-install health checks
            $checks        = $this->validator->runAll($ctx);
            $overallStatus = $this->validator->overallStatus($checks);
            $requiredPass  = $this->validator->allRequiredPass($checks);

            // Persist validation results on the installation record
            $ctx->installation->update([
                'post_install_validation' => [
                    'status'     => $overallStatus,
                    'checks'     => $checks,
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);

            // Hard fail only if required checks (database, storage) fail
            if (!$requiredPass) {
                $failed = array_keys(array_filter($checks, fn($c) => $c['required'] && $c['status'] !== 'PASS'));
                return StageResult::failure(
                    $this->name(),
                    'Post-install validation: required checks FAILED — ' . implode(', ', $failed),
                    $this->elapsed()
                );
            }

            // Mark installation complete
            $this->state->markComplete($ctx->installation);

            // Slack notification
            $this->notifySlack($ctx, $checks, $overallStatus);

            $warnings = array_keys(array_filter($checks, fn($c) => $c['status'] === 'WARN'));

            return StageResult::success($this->name(), [
                'domain'           => $req->domain,
                'validation_status'=> $overallStatus,
                'checks'           => $checks,
                'warnings'         => $warnings,
                'site_id'          => $ctx->installation->site_id,
            ], [], $this->elapsed());
        });
    }

    private function notifySlack(InstallationContext $ctx, array $checks, string $status): void
    {
        try {
            $req    = $ctx->request;
            $icon   = $status === 'PASS' ? '✅' : ($status === 'WARN' ? '⚠️' : '❌');
            $detail = collect($checks)
                ->map(fn($c, $k) => "{$k}: {$c['status']}")
                ->implode(' | ');

            $this->slack->info(
                "{$icon} [INSTALL-{$status}] {$req->company} @ {$req->domain}",
                'Customer Installation',
                "Site ID: {$ctx->installation->site_id} | Mode: {$req->installMode} | {$detail}"
            );
        } catch (\Throwable) {}
    }
}
