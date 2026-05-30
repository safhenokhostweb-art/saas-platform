<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeHealthService;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\ReverbRuntimeValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\RedisRuntimeValidator;

class RealtimeHealthCommand extends Command
{
    protected $signature = 'mobile:realtime-health
        {--json             : Output as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}
        {--tenant=          : Check health for specific tenant}';

    protected $description = 'Show detailed mobile realtime health: Reverb, Redis, transport, channel registry';

    public function handle(
        MobileRealtimeHealthService $healthService,
        ReverbRuntimeValidator $reverbValidator,
        RedisRuntimeValidator $redisValidator,
    ): int {
        $tenantId = $this->option('tenant') ?? 'platform';
        $strict   = (bool) $this->option('strict');
        $failWarn = (bool) $this->option('fail-on-warning');

        $health  = $healthService->getHealth($tenantId);
        $checks  = $healthService->getChecks($tenantId);
        $reverb  = $reverbValidator->validate();
        $redis   = $redisValidator->validate();

        $score   = $health['score'];
        $warned  = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
        $failed  = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($failWarn && $warned > 0) return self::FAILURE;
            return $score >= 70 ? self::SUCCESS : self::FAILURE;
        }

        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Realtime Health</>');
        $this->line(str_repeat('─', 55));

        // Reverb section
        $reverbColor = $reverb['production_ready'] ? 'green' : 'yellow';
        $this->line('<options=bold>  WebSocket Transport (Reverb)</>');
        $this->line("    Status           : <fg={$reverbColor}>{$reverb['status']}</>");
        $this->line("    Transport mode   : <fg={$reverbColor}>{$reverb['transport']}</>");
        $this->line("    Message          : {$reverb['message']}");
        if (! empty($reverb['missing'])) {
            $this->line("    Missing          : " . implode(', ', $reverb['missing']));
        }
        $this->newLine();

        // Redis section
        $redisColor = $redis['production_ready'] ? 'green' : 'yellow';
        $this->line('<options=bold>  Cache / Presence Store (Redis)</>');
        $this->line("    Status           : <fg={$redisColor}>{$redis['status']}</>");
        $this->line("    Driver           : {$redis['driver']}");
        $this->line("    Production ready : " . ($redis['production_ready'] ? '<fg=green>Yes</>' : '<fg=yellow>No (fallback)</>'));
        $this->newLine();

        // Dimension checks
        $this->line('<options=bold>  Health Dimensions</>');
        foreach ($checks as $check) {
            $icon = match ($check['status']) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("    {$icon}  {$check['name']}: {$check['score']}/100");
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Realtime Health Score: {$score}/100</> — Transport: {$health['transport']}");
        $this->newLine();

        if ($score >= 90) {
            $this->info('Realtime: Production-Ready ✓');
        } elseif ($score >= 70) {
            $this->warn('Realtime: Degraded — configure Reverb and Redis for full capability');
        } else {
            $this->error('Realtime: Not ready for production');
        }

        if ($failWarn && $warned > 0) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
