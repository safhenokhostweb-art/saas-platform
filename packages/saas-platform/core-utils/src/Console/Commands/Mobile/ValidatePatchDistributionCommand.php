<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Security\PatchGovernanceHealthService;
use SaaSPlatform\CoreUtils\MobileRuntime\Security\SecurityPatchRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\Security\MobilePatchRegistryAdapter;

class ValidatePatchDistributionCommand extends Command
{
    protected $signature = 'mobile:validate-patch-distribution
        {--json             : Output as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}';

    protected $description = 'Validate security patch registry, distribution, and mobile runtime sync';

    public function handle(
        PatchGovernanceHealthService $healthService,
        SecurityPatchRegistry $registry,
        MobilePatchRegistryAdapter $adapter,
    ): int {
        $strict   = (bool) $this->option('strict');
        $failWarn = (bool) $this->option('fail-on-warning');

        $health = $healthService->getHealth();
        $checks = $healthService->getChecks();
        $sync   = $adapter->syncStatus();

        if ($strict) {
            foreach ($checks as &$check) {
                if ($check['status'] === 'warn') $check['status'] = 'fail';
            }
            unset($check);
        }

        $score   = $health['score'];
        $warned  = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
        $failed  = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'           => $score,
                'grade'           => $health['grade'],
                'registry_count'  => $health['registry_count'],
                'mobile_count'    => $health['mobile_count'],
                'critical_count'  => $health['critical_count'],
                'in_sync'         => $health['in_sync'],
                'checks'          => $checks,
                'sync'            => $sync,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($failWarn && $warned > 0) return self::FAILURE;
            return $score >= 70 ? self::SUCCESS : self::FAILURE;
        }

        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Security Patch Distribution Validation</>');
        $this->line(str_repeat('─', 55));
        $this->line("  Platform registry  : <fg=green>{$health['registry_count']}</> patches");
        $this->line("  Mobile runtime     : <fg=green>{$health['mobile_count']}</> patches synced");
        $this->line("  Critical patches   : " . ($health['critical_count'] > 0 ? "<fg=yellow>{$health['critical_count']}</>" : '<fg=green>0</>'));
        $this->line("  Registry sync      : " . ($health['in_sync'] ? '<fg=green>In sync</>' : '<fg=yellow>Drift detected</>'));
        $this->newLine();

        foreach ($checks as $check) {
            $icon = match ($check['status']) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("  {$icon}  {$check['message']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Patch Distribution Score: {$score}/100 (Grade {$health['grade']})</>");
        $this->newLine();

        if ($score >= 90) {
            $this->info('Patch Distribution: Enterprise-Grade ✓');
        } elseif ($score >= 70) {
            $this->warn('Patch Distribution: Operational — review issues');
        } else {
            $this->error('Patch Distribution: Action required');
        }

        if ($failWarn && $warned > 0) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
