<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Telemetry;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueueTelemetryService;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueuePerformanceAnalyzer;

class QueueTelemetryCommand extends Command
{
    protected $signature   = 'queue:telemetry {--json : Output as JSON}';
    protected $description = 'Display queue telemetry snapshot';

    public function handle(
        QueueTelemetryService  $telemetry,
        QueuePerformanceAnalyzer $analyzer
    ): int {
        $snapshot    = $telemetry->snapshot();
        $performance = $analyzer->analyze();

        $result = array_merge($snapshot, [
            'performance' => $performance,
            'generated_at' => now()->toIso8601String(),
        ]);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('Queue Telemetry');
        $this->line('Pending jobs : ' . $snapshot['pending']);
        $this->line('Failed jobs  : ' . $snapshot['failed']);
        $this->line('Health       : ' . $performance['health']);

        if (!empty($snapshot['queues'])) {
            $this->line('');
            $this->info('Queue Depths:');
            foreach ($snapshot['queues'] as $queue => $count) {
                $this->line("  {$queue}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
