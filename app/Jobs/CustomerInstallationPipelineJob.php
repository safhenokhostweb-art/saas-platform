<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerInstallationPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public readonly int $installationId) {}

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
