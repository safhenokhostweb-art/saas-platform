<?php
namespace App\Jobs;

use App\Models\Infrastructure\RestoreLog;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestorePlatformStorageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 1800;

    public function __construct(
        public readonly int    $restoreLogId,
        public readonly string $remotePath,
        public readonly string $mergeStrategy = 'merge',
    ) {
        $this->onQueue('restore');
    }

    public function handle(RestoreService $service): void
    {
        $log = RestoreLog::findOrFail($this->restoreLogId);
        $log->markRunning();

        $service->executePlatformStorageRestore($log, $this->remotePath, $this->mergeStrategy);
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
            'RestorePlatformStorageJob crashed: ' . $e->getMessage()
        );
    }
}
