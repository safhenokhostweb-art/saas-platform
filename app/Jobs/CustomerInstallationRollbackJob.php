<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerInstallationRollbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 3;

    /** Backoff in seconds: 60s then 5 minutes before the 3rd attempt. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public readonly int $installationId) {}

    /**
     * Phase 13-O6 — a rollback always belongs to exactly one installation
     * (CustomerInstallationRollbackService replays that installation's own
     * checkpoints), so two overlapping rollback runs for the same
     * installation — one automatic (via RollbackDispatcher) racing one
     * manually triggered from CustomerDeploymentController::rollback(), for
     * example — could otherwise replay the same LIFO checkpoints from two
     * concurrent SSH sessions at once, including the database restore in
     * BackupModuleUpgradeStage::rollback(). expireAfter is set above the
     * job's own $timeout (1800s) so the lock outlives a normal run and only
     * a crashed worker leaves it to expire on its own; releaseAfter keeps a
     * job that loses the race from hammering the lock immediately after.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("rollback-installation-{$this->installationId}"))
                ->releaseAfter(30)
                ->expireAfter(1900),
        ];
    }

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
                ['installation_id' => $this->installationId, 'error' => $exception->getMessage()]
            );
        } catch (\Throwable) {}
    }
}
