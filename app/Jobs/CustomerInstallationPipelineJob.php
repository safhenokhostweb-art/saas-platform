<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\RollbackDispatcher;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\ModuleUpdateEligibilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerInstallationPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public readonly int $installationId) {}

    /**
     * Phase 13-O1 — bounded by wall-clock time rather than attempt count.
     * The pipeline's own failures never throw out of handle() (Orchestrator
     * catches and resolves them internally via markFailed()/dispatchRollback()),
     * so the only things that consume an attempt here are infra failures
     * (timeout/crash) and WithoutOverlapping releasing the job while it waits
     * for another installation's lock on the same site. A fixed $tries would
     * conflate those two very different situations; retryUntil() lets the job
     * keep waiting out lock contention without an arbitrary attempt ceiling.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(90);
    }

    /**
     * Phase 13-O1 — prevents two installations from running concurrently
     * against the same tenant/site. Keyed by site_id where known (module
     * updates always have it; fresh installs get it only from
     * ProvisionLicenseStage onward), falling back to the installation id
     * itself so unrelated fresh installs (site_id still null) never share
     * a lock key.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->lockKey()))
                ->releaseAfter(30)
                ->expireAfter(3700),
        ];
    }

    private function lockKey(): string
    {
        $siteId = CustomerInstallation::find($this->installationId)?->site_id;

        return $siteId
            ? "cie-site-{$siteId}"
            : "cie-install-{$this->installationId}";
    }

    public function handle(CustomerInstallationOrchestrator $orchestrator): void
    {
        $installation = CustomerInstallation::find($this->installationId);

        if (!$installation) {
            Log::error("CIE: Installation {$this->installationId} not found");
            return;
        }

        if ($installation->isComplete()) {
            Log::info("CIE: Installation {$this->installationId} already complete — skipping");
            return;
        }

        Log::info("CIE: Starting pipeline for installation {$this->installationId}");

        $orchestrator->run($installation);

        // Phase 17-C — Part 5: the orchestrator itself never throws out of
        // run() (every stage/capability/unexpected-error path is caught
        // internally and resolved via markFailed()), so this is the one
        // reliable point after ANY module pipeline outcome — success or
        // failure, from any stage — to reconcile site_modules.deployment_status.
        // Deliberately outside CustomerInstallationOrchestrator/Stages: this
        // touches an unrelated table (site_modules), not pipeline behavior.
        $this->syncModuleDeploymentStatus($installation->fresh());
    }

    /**
     * Phase 17-C — Part 5: no-op for every install_mode except
     * MODE_MODULE_UPDATE/MODE_MODULE_INSTALL. A 'complete' installation is
     * already synced by CompleteModuleUpgradeStage itself (it has the
     * target version in hand, this method does not); anything else
     * (failed/rollback_failed/rolled_back) is marked failed here so no
     * site_modules row is ever left sitting in pending_install/installing/
     * upgrading after the pipeline has actually stopped running.
     */
    private function syncModuleDeploymentStatus(?CustomerInstallation $installation): void
    {
        if (!$installation || $installation->isComplete()) {
            return;
        }

        if (!in_array($installation->install_mode, ['MODE_MODULE_UPDATE', 'MODE_MODULE_INSTALL'], true)) {
            return;
        }

        $siteId = $installation->site_id;
        $moduleSlug = $installation->metadata['module_slug'] ?? null;

        if (!$siteId || !$moduleSlug) {
            return;
        }

        try {
            app(ModuleUpdateEligibilityService::class)->markDeploymentFailed((int) $siteId, $moduleSlug);
        } catch (\Throwable $e) {
            Log::error('CIE: module deployment_status sync failed', [
                'installation_id' => $installation->id,
                'site_id' => $siteId,
                'module_slug' => $moduleSlug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CIE: Pipeline job failed", [
            'installation_id' => $this->installationId,
            'error' => $exception->getMessage(),
        ]);

        $installation = CustomerInstallation::find($this->installationId);

        if ($installation && $installation->isRunning()) {
            $installation->update([
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => 'Queue job failed: ' . $exception->getMessage(),
            ]);
        }

        $this->syncModuleDeploymentStatus($installation?->fresh());

        try {
            app(SlackAlertService::class)->critical(
                "[INSTALL FAILED] Installation {$this->installationId}",
                $exception->getMessage()
            );
        } catch (\Throwable) {}

        // Phase 13-O3 — this is the one failure path the orchestrator itself
        // never sees (queue timeout, worker crash, exhausted retries), so it's
        // the only place that ever needs to trigger rollback from here rather
        // than from CustomerInstallationOrchestrator::run()'s own handling.
        if (!$installation) {
            return;
        }

        $dispatched = app(RollbackDispatcher::class)->dispatch($installation);

        if (!$dispatched) {
            try {
                app(SlackAlertService::class)->critical(
                    "[ROLLBACK DISPATCH FAILED] Installation {$this->installationId} — Manual intervention required",
                    'The pipeline job failed and automatic rollback could not be queued. The tenant may be left mid-upgrade until this is resolved manually.'
                );
            } catch (\Throwable) {}
        }
    }
}
