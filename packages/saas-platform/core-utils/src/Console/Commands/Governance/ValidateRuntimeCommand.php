<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Governance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceRuntimeValidationService;

class ValidateRuntimeCommand extends Command
{
    protected $signature = 'governance:validate-runtime
                            {--strict : Treat warnings as failures}
                            {--json   : Output as JSON}';

    protected $description = 'Validate the autonomous runtime governance stack';

    public function handle(GovernanceRuntimeValidationService $validator): int
    {
        $result = $validator->validate();
        $strict = $this->option('strict');

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return ($result['valid'] && (!$strict || $result['warnings'] === 0)) ? 0 : 1;
        }

        $this->info('=== Governance Runtime Validation ===');
        $this->line('Validated at: ' . $result['validated_at']);
        $this->newLine();

        foreach ($result['checks'] as $name => $check) {
            $icon = match ($check['status']) {
                'pass' => '<fg=green>PASS</>',
                'warn' => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            };
            $this->line(sprintf('  [%s] %-25s %s', $icon, $name, $check['detail']));
        }

        $this->newLine();
        $this->line(sprintf(
            'Results: %d passed, %d warnings, %d failed (of %d)',
            $result['passed'], $result['warnings'], $result['failed'], $result['total']
        ));

        $ok = $result['valid'] && (!$strict || $result['warnings'] === 0);

        if ($ok) {
            $this->info('Governance runtime validation PASSED.');
        } else {
            $this->error('Governance runtime validation FAILED.');
        }

        return $ok ? 0 : 1;
    }
}
