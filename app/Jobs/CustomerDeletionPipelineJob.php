<?php

namespace App\Jobs;

use App\Models\CustomerDeletion;
use App\Services\CustomerDeletion\CustomerDeleteOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ADR-090 — unique per deletion while queued (a double-clicked Retry cannot
 * enqueue a second copy); CustomerDeleteOrchestrator additionally holds a
 * cache lock while it runs.
 */
class CustomerDeletionPipelineJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 3;

    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'customer-deletion-' . $this->deletionId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public readonly int $deletionId,
    ) {}

    public function handle(CustomerDeleteOrchestrator $orchestrator): void
    {
        $deletion = CustomerDeletion::with('installation.remoteServer')->find($this->deletionId);

        if (!$deletion) {
            Log::error("CDE: CustomerDeletion #{$this->deletionId} not found");
            return;
        }

        if ($deletion->isComplete()) {
            Log::info("CDE: Deletion #{$this->deletionId} already complete — skipping");
            return;
        }

        $orchestrator->run($deletion);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("CDE: CustomerDeletionPipelineJob failed for deletion #{$this->deletionId}", [
            'error' => $e->getMessage(),
        ]);

        $deletion = CustomerDeletion::find($this->deletionId);
        if ($deletion && !$deletion->isComplete() && !$deletion->isFailed()) {
            $deletion->update([
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => 'Job failed: ' . $e->getMessage(),
            ]);
        }
    }
}
