<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Scalability;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Scalability\ScalabilityValidationService;

class ValidateScalabilityCommand extends Command
{
    protected $signature = 'scalability:validate
                            {--strict : Treat warnings as failures}
                            {--json   : Output as JSON}';

    protected $description = 'Validate the scalability governance stack';

    public function handle(ScalabilityValidationService $validator): int
    {
        $result = $validator->validate();
        $strict = $this->option('strict');

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return ($result['valid'] && (!$strict || $result['warnings'] === 0)) ? 0 : 1;
        }

        $this->info('=== Scalability Validation ===');
        $this->line('Validated at: ' . $result['validated_at']);
        $this->newLine();

        foreach ($result['checks'] as $name => $check) {
            $icon = match ($check['status']) {
                'pass' => '<fg=green>PASS</>',
                'warn' => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            };
            $this->line(sprintf('  [%s] %-28s %s', $icon, $name, $check['detail']));
        }

        $this->newLine();
        $this->line(sprintf('Results: %d passed, %d warnings, %d failed (of %d)',
            $result['passed'], $result['warnings'], $result['failed'], $result['total']));

        $ok = $result['valid'] && (!$strict || $result['warnings'] === 0);
        $ok ? $this->info('Scalability validation PASSED.') : $this->error('Scalability validation FAILED.');

        return $ok ? 0 : 1;
    }
}
