<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\Infrastructure\SlackAlertService;
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

        try {
            app(SlackAlertService::class)->critical(
                "[INSTALL FAILED] Installation {$this->installationId}",
                'Customer Installation',
                $exception->getMessage()
            );
        } catch (\Throwable) {}
    }
}
