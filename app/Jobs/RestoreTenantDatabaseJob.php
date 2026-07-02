<?php
namespace App\Jobs;

use App\Models\Infrastructure\RestoreLog;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreTenantDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        public readonly int    $restoreLogId,
        public readonly string $remotePath,
        public readonly int    $siteId,
    ) {
        $this->onQueue('restore');
    }

    public function handle(RestoreService $service): void
    {
        $log = RestoreLog::findOrFail($this->restoreLogId);
        if ($log->status !== RestoreLog::STATUS_PENDING) {
            return;
        }
        $log->markRunning();

        $service->executeTenantDatabaseRestore($log, $this->remotePath, $this->siteId);
    }

    public function failed(\Throwable $e): void
    {
        $log = RestoreLog::find($this->restoreLogId);
        if ($log && $log->status === RestoreLog::STATUS_RUNNING) {
            $log->markFailed('Job failed unexpectedly: ' . $e->getMessage());
        }

        $service = app(\App\Services\Infrastructure\RestoreService::class);
        $service->releaseLock();
        $service->stopRestoreWorker();
        app(\App\Services\Infrastructure\SlackAlertService::class)->critical(
            'Restore Job Crashed',
            "RestoreTenantDatabaseJob for site {$this->siteId} crashed: " . $e->getMessage()
        );
    }
}
