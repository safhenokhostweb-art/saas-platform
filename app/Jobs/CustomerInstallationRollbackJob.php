<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerInstallationRollbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public readonly int $installationId) {}

    public function handle(CustomerInstallationRollbackService $rollback): void
    {
        $installation = CustomerInstallation::find($this->installationId);

        if (!$installation) {
            Log::error("CIE Rollback: Installation {$this->installationId} not found");
            return;
        }

        Log::info("CIE: Initiating rollback for installation {$this->installationId}");

        $rollback->rollback($installation);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CIE: Rollback job failed", [
            'installation_id' => $this->installationId,
            'error' => $exception->getMessage(),
        ]);

        $installation = CustomerInstallation::find($this->installationId);
        $installation?->update(['status' => 'rollback_failed']);

        try {
            app(SlackAlertService::class)->critical(
                "[ROLLBACK FAILED] Installation {$this->installationId} — Manual intervention required",
                'Customer Installation Rollback',
                $exception->getMessage()
            );
        } catch (\Throwable) {}
    }
}
