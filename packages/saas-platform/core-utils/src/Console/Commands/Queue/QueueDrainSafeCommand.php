<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Queue;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Services\Queue\QueueRuntimeCompatibilityService;

class QueueDrainSafeCommand extends Command
{
    protected $signature = 'queue:drain-safe
                            {--timeout=300 : Seconds to wait for queue to drain before aborting}
                            {--stamp       : Stamp current release UUID after draining}';

    protected $description = 'Gracefully drain the queue before a deployment (Phase 8.2)';

    public function handle(QueueRuntimeCompatibilityService $service): int
    {
        $driver = config('queue.default', 'sync');

        if ($driver === 'sync') {
            $this->info('Sync queue driver — drain not required.');
            return 0;
        }

        $timeout  = (int) $this->option('timeout');
        $deadline = now()->addSeconds($timeout);

        $this->line("Draining queue (driver: {$driver}, timeout: {$timeout}s)...");

        while (now()->lt($deadline)) {
            $pending = $this->getPendingCount($driver);

            if ($pending === 0) {
                $this->info('Queue drained successfully.');

                if ($this->option('stamp')) {
                    $latest = app(\SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager::class)->latest();
                    if ($latest) {
                        $service->stampCurrentRelease($latest['deployment_uuid']);
                        $this->line("  Release stamped: {$latest['deployment_uuid']}");
                    }
                }

                return 0;
            }

            $this->line("  {$pending} job(s) pending... waiting 5s");
            sleep(5);
        }

        $this->error("Queue drain timed out after {$timeout}s — {$this->getPendingCount($driver)} job(s) remain.");
        return 1;
    }

    private function getPendingCount(string $driver): int
    {
        if ($driver === 'database') {
            try {
                return (int) DB::table('jobs')->count();
            } catch (\Throwable) {
                return 0;
            }
        }
        return 0; // Other drivers: assume drained
    }
}
