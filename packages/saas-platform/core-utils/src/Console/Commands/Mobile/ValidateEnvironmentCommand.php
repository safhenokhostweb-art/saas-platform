<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Validation\MobileEnvironmentValidator;

class ValidateEnvironmentCommand extends Command
{
    protected $signature = 'mobile:validate-environment
        {--json             : Output as JSON}
        {--strict           : Treat warnings as failures}
        {--fail-on-warning  : Exit 1 if any warnings exist}';

    protected $description = 'Validate mobile runtime environment: Reverb, Redis, Queue, Push, OTA, Storage, API';

    public function handle(MobileEnvironmentValidator $validator): int
    {
        $strict   = (bool) $this->option('strict');
        $failWarn = (bool) $this->option('fail-on-warning');

        $result = $validator->validate();

        if ($strict) {
            foreach ($result['checks'] as &$check) {
                if ($check['status'] === 'warn') $check['status'] = 'fail';
            }
            unset($check);
            $result['passed'] = count(array_filter($result['checks'], fn ($c) => $c['status'] === 'pass'));
            $result['failed'] = count(array_filter($result['checks'], fn ($c) => $c['status'] === 'fail'));
            $result['score']  = (int) round($result['passed'] / $result['total'] * 100);
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($failWarn && $result['warned'] > 0) return self::FAILURE;
            return $result['score'] >= 70 ? self::SUCCESS : self::FAILURE;
        }

        $score = $result['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Runtime Environment Validation</>');
        $this->line("  Environment: <fg=yellow>{$result['environment']}</>");
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
        $this->line("<fg={$color};options=bold>Environment Score: {$score}/100 (Grade {$result['grade']})</> — {$result['passed']}/{$result['total']} checks passed");
        $this->newLine();

        if ($result['production_ready']) {
            $this->info('Environment: Production-Ready ✓');
        } elseif ($score >= 70) {
            $this->warn('Environment: Functional but not production-ready — see issues above');
        } else {
            $this->error('Environment: Critical configuration missing — resolve issues before production deployment');
        }

        if ($failWarn && $result['warned'] > 0) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
