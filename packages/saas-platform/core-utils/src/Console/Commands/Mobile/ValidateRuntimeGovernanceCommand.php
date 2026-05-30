<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeValidationService;

class ValidateRuntimeGovernanceCommand extends Command
{
    protected $signature = 'mobile:validate-runtime-governance
        {--json             : Output results as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}';

    protected $description = 'Validate the mobile runtime governance configuration across all dimensions';

    public function handle(MobileRuntimeValidationService $service): int
    {
        $result  = $service->validate();
        $strict  = (bool) $this->option('strict');
        $failWarn = (bool) $this->option('fail-on-warning');

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['score'] >= 80 ? self::SUCCESS : self::FAILURE;
        }

        $score = $result['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Runtime Governance Validation</>');
        $this->line(str_repeat('─', 55));

        foreach ($result['checks'] as $check) {
            $status = $check['status'];
            if ($strict && $status === 'warn') $status = 'fail';
            $icon   = match ($status) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("  {$icon}  {$check['message']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Score: {$score}/100 (Grade {$result['grade']})</> — {$result['passed']}/{$result['total']} checks passed");
        $this->newLine();

        if ($score >= 90) {
            $this->info('Mobile Runtime Governance: Enterprise-Ready ✓');
        } elseif ($score >= 70) {
            $this->warn('Mobile Runtime Governance: Needs attention');
        } else {
            $this->error('Mobile Runtime Governance: Critical issues must be resolved');
        }

        $hasWarnings = ($result['warned'] ?? 0) > 0;
        if ($failWarn && $hasWarnings) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
