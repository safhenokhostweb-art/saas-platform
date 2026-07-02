<?php

namespace App\Jobs;

use App\Models\CustomerDeletion;
use App\Services\CustomerDeletion\CustomerDeleteOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerDeletionPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 3;

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
