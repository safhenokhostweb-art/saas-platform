<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Telemetry;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Telemetry\Queue\FailedJobDiagnosticsService;
use SaaSPlatform\CoreUtils\Telemetry\Queue\WorkerHealthTracker;

class QueueDiagnosticsCommand extends Command
{
    protected $signature   = 'queue:diagnostics {--json : Output as JSON} {--job= : Diagnose specific failed job by ID}';
    protected $description = 'Run queue diagnostics and failed job analysis';

    public function handle(
        FailedJobDiagnosticsService $diagnostics,
        WorkerHealthTracker         $tracker
    ): int {
        if ($jobId = $this->option('job')) {
            $result = $diagnostics->diagnoseById((int) $jobId);

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            if (!$result['found']) {
                $this->error("Failed job #{$jobId} not found.");
                return self::FAILURE;
            }

            $this->info("Failed Job #{$jobId}");
            $this->line('Queue     : ' . $result['queue']);
            $this->line('Failed at : ' . $result['failed_at']);
            $this->line('Exception : ' . $result['exception_class']);
            return self::SUCCESS;
        }

        $summary = $diagnostics->summary();

        $result = [
            'diagnostics'    => $summary,
            'current_release' => $tracker->currentReleaseStamp(),
            'generated_at'   => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        if (!($summary['available'] ?? false)) {
            $this->warn('Queue diagnostics unavailable (failed_jobs table missing).');
            return self::SUCCESS;
        }

        $this->info('Queue Diagnostics');
        $this->line('Total failed    : ' . $summary['total']);
        $this->line('Current release : ' . ($tracker->currentReleaseStamp() ?? 'none'));

        if (!empty($summary['by_queue'])) {
            $this->line('');
            $this->info('Failures by Queue:');
            foreach ($summary['by_queue'] as $queue => $count) {
                $this->line("  {$queue}: {$count}");
            }
        }

        if (!empty($summary['by_exception'])) {
            $this->line('');
            $this->info('Top Exception Classes:');
            foreach ($summary['by_exception'] as $ex => $count) {
                $this->line("  [{$count}x] {$ex}");
            }
        }

        return self::SUCCESS;
    }
}
