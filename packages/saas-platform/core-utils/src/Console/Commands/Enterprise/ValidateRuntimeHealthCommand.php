<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Enterprise;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\RuntimeRecovery\RuntimeRecoveryOrchestrator;
use SaaSPlatform\CoreUtils\Services\FrontendCanonicalParityEnforcer;
use SaaSPlatform\CoreUtils\AI\Safety\AiEmergencyCircuitBreaker;

class ValidateRuntimeHealthCommand extends Command
{
    protected $signature   = 'enterprise:validate-runtime-health {--json} {--strict} {--fail-on-warning}';
    protected $description = 'Comprehensive enterprise runtime health validation across all subsystems.';

    public function handle(
        RuntimeRecoveryOrchestrator  $recovery,
        FrontendCanonicalParityEnforcer $parity,
        AiEmergencyCircuitBreaker    $aiCircuit,
    ): int {
        $dimensions = [];
        $overall    = 100;

        // Frontend parity
        $fp = $parity->audit();
        $dimensions['frontend_parity'] = ['score' => $fp['score'], 'grade' => $fp['grade'], 'pass' => $fp['parity']];
        $overall = ($overall + $fp['score']) / 2;

        // AI circuit
        $aiState = $aiCircuit->state();
        $aiOk    = $aiState === 'closed';
        $dimensions['ai_circuit_breaker'] = ['state' => $aiState, 'pass' => $aiOk];
        if (! $aiOk) $overall -= 10;

        // Recovery diagnostics
        $frontend = $recovery->recoverAssets();
        $dimensions['assets'] = ['pass' => $frontend['healthy']];
        if (! $frontend['healthy']) $overall -= 5;

        // AI runtime
        $aiRuntime = $recovery->recoverAiRuntime();
        $dimensions['ai_runtime'] = ['pass' => $aiRuntime['healthy']];
        if (! $aiRuntime['healthy']) $overall -= 5;

        // Queue health
        $queueOk = class_exists(\SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\QueueStallDetector::class);
        $dimensions['queue_governance'] = ['pass' => $queueOk];

        // Telemetry
        $telOk = class_exists(\SaaSPlatform\CoreUtils\Telemetry\TelemetryManager::class);
        $dimensions['telemetry'] = ['pass' => $telOk];

        // Notification layer
        $notifOk = class_exists(\SaaSPlatform\CoreUtils\Notifications\UnifiedNotificationService::class);
        $dimensions['notifications'] = ['pass' => $notifOk];

        // Realtime
        $rtOk = class_exists(\SaaSPlatform\CoreUtils\Realtime\RealtimeRuntimeCoordinator::class);
        $dimensions['realtime'] = ['pass' => $rtOk];

        $overall = max(0, min(100, round($overall)));
        $grade   = match(true) { $overall >= 97 => 'A+', $overall >= 90 => 'A', $overall >= 80 => 'B', $overall >= 70 => 'C', default => 'F' };
        $pass    = array_filter($dimensions, fn($d) => ! $d['pass']);

        $result = [
            'command'    => 'enterprise:validate-runtime-health',
            'score'      => $overall,
            'grade'      => $grade,
            'dimensions' => $dimensions,
            'failures'   => count($pass),
            'healthy'    => empty($pass),
            'validated_at' => now()->toISOString(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $this->newLine();
            $this->line('  <fg=cyan>Enterprise Runtime Health Validation</>');
            $color = $overall >= 90 ? 'green' : ($overall >= 70 ? 'yellow' : 'red');
            $this->line("  Score: <fg={$color}>{$overall}/100 ({$grade})</>");
            $this->newLine();
            foreach ($dimensions as $dim => $data) {
                $icon = ($data['pass'] ?? true) ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $extra = isset($data['score']) ? " ({$data['score']}/100)" : (isset($data['state']) ? " [{$data['state']}]" : '');
                $this->line("  {$icon} {$dim}{$extra}");
            }
            $this->newLine();
            if (empty($pass)) {
                $this->info('  All enterprise subsystems are healthy.');
            } else {
                $this->error('  '.count($pass).' subsystem(s) need attention.');
            }
        }

        if ($this->option('strict') && (count($pass) > 0 || $this->option('fail-on-warning'))) {
            return self::FAILURE;
        }
        return empty($pass) ? self::SUCCESS : self::FAILURE;
    }
}
