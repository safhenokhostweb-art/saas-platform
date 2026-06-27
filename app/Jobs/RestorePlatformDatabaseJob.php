<?php
namespace App\Jobs;

use App\Models\Infrastructure\RestoreLog;
use App\Services\Infrastructure\RestoreService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestorePlatformDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 1;       // No retries — too dangerous for destructive operations
    public int    $timeout = 3600;    // 1 hour max — large databases
    public string $queue   = 'restore';

    public function __construct(
        public readonly int    $restoreLogId,
        public readonly string $remotePath,
    ) {}

    public function handle(RestoreService $service): void
    {
        $log = RestoreLog::findOrFail($this->restoreLogId);
        $log->markRunning();

        $service->executePlatformDatabaseRestore($log, $this->remotePath);
    }

    public function failed(\Throwable $e): void
    {
        $log = RestoreLog::find($this->restoreLogId);
        if ($log && $log->status === RestoreLog::STATUS_RUNNING) {
            $log->markFailed('Job failed unexpectedly: ' . $e->getMessage());
        }

        app(RestoreService::class)->releaseLock();

        // Ensure maintenance mode is off if job crashed mid-restore
        try {
            \Illuminate\Support\Facades\Artisan::call('up');
        } catch (\Throwable) {}

        app(SlackAlertService::class)->critical(
            'Platform DB Restore Job Crashed',
            "RestorePlatformDatabaseJob crashed: " . $e->getMessage() . "\n⚠️ Maintenance mode may still be active — check immediately."
        );
    }
}
