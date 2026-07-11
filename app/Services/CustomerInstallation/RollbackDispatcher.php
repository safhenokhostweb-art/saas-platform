<?php

namespace App\Services\CustomerInstallation;

use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use Illuminate\Support\Facades\Log;

/**
 * Phase 13-O3 — single entry point for queuing CustomerInstallationRollbackJob.
 * Extracted from CustomerInstallationOrchestrator so the same dispatch logic
 * is shared by every failure path that needs to trigger a rollback:
 * the orchestrator's own stage/pipeline failure handling,
 * CustomerInstallationPipelineJob::failed() (queue-level failures the
 * orchestrator never gets a chance to react to), and any future recovery
 * sweep that finds installations stuck without a rollback ever having been
 * queued.
 */
class RollbackDispatcher
{
    /**
     * @return bool true if the rollback job was queued, false if dispatch itself failed.
     */
    public function dispatch(CustomerInstallation $installation): bool
    {
        try {
            CustomerInstallationRollbackJob::dispatch($installation->id)
                ->onConnection('customer-install')
                ->onQueue('customer-install');

            return true;
        } catch (\Throwable $e) {
            Log::error('CIE: Failed to dispatch rollback job', [
                'installation_id' => $installation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
