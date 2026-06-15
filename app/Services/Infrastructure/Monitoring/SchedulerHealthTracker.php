<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Infrastructure\InfraSchedulerRun;
use Illuminate\Support\Facades\Log;

class SchedulerHealthTracker
{
    public function __construct(private InfrastructureErrorCollector $collector) {}

    public function track(string $command, callable $fn): void
    {
        $run = InfraSchedulerRun::startRun($command);

        try {
            ob_start();
            $fn();
            $output = ob_get_clean() ?: null;
            $run->endSuccess($output);
        } catch (\Throwable $e) {
            ob_end_clean();
            $run->endFailed($e->getMessage());

            Log::error("SchedulerHealthTracker: [{$command}] failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $this->collector->collectSchedulerFailure($command, $e->getMessage());
            } catch (\Throwable) {}

            throw $e;
        }
    }
}
