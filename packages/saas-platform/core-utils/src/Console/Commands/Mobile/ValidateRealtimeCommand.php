<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRealtimeDiagnostics;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeCoordinator;

class ValidateRealtimeCommand extends Command
{
    protected $signature = 'mobile:validate-realtime
        {--json             : Output results as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}';

    protected $description = 'Validate mobile realtime transport, Reverb configuration and WebSocket health';

    public function handle(MobileRealtimeDiagnostics $diagnostics, MobileRealtimeCoordinator $coordinator): int
    {
        $result   = $diagnostics->runDiagnostics('platform');
        $rtHealth = $diagnostics->getRealtimeHealth();

        if ($this->option('json')) {
            $this->line(json_encode(array_merge($result, ['health' => $rtHealth]), JSON_PRETTY_PRINT));
            return $result['score'] >= 80 ? self::SUCCESS : self::FAILURE;
        }

        $score = $result['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Realtime Transport Validation</>');
        $this->line(str_repeat('─', 55));

        foreach ($result['checks'] as $check) {
            $icon = match ($check['status']) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("  {$icon}  {$check['name']}: {$check['message']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Realtime Score: {$score}/100</> — Transport: {$rtHealth['transport']}");
        $this->newLine();

        if (! empty($result['issues'])) {
            $this->warn('Issues detected:');
            foreach ($result['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        }

        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
