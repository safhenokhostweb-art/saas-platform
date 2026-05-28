<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\SecurityCenter;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\SecurityCenter\Governance\SecurityCenterGovernanceValidationService;

class ValidateSecurityCenterCommand extends Command
{
    protected $signature = 'security-center:validate
        {--json : Output as JSON}
        {--strict : Treat warnings as failures}
        {--fail-on-warning : Exit 1 if any warnings found}';

    protected $description = 'Validate Security Center governance — checks frontend parity, patch availability, trust integrity, canonical UI, and policy enforcement';

    public function handle(SecurityCenterGovernanceValidationService $validator): int
    {
        $strict = $this->option('strict') || $this->option('fail-on-warning');
        $result = $validator->validate($strict);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['status'] === 'pass' ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Security Center Governance Validation</>');
        $this->line('  ' . str_repeat('─', 50));
        $this->newLine();

        foreach ($result['checks'] as $check => $data) {
            $icon   = match($data['status']) { 'pass' => '<fg=green>✓</>', 'warning' => '<fg=yellow>⚠</>', default => '<fg=red>✗</>' };
            $label  = str_pad(str_replace('_', ' ', ucwords($check, '_')), 35);
            $this->line("  {$icon}  {$label} {$data['message']}");
        }

        $this->newLine();
        $scoreColor = $result['score'] >= 90 ? 'green' : ($result['score'] >= 70 ? 'yellow' : 'red');
        $this->line("  Score: <fg={$scoreColor};options=bold>{$result['score']}/100 ({$result['grade']})</>");
        $this->line("  Passing: {$result['passing']}/{$result['total']}  Warnings: {$result['warnings']}  Failures: {$result['failures']}");
        $this->newLine();

        if ($result['status'] === 'pass') {
            $this->info('  ✓ Security Center governance validation passed.');
        } elseif ($result['status'] === 'warning') {
            $this->warn('  ⚠ Validation passed with warnings. Use --strict to treat as failures.');
        } else {
            $this->error('  ✗ Security Center governance validation FAILED.');
        }

        $this->newLine();
        return $result['status'] === 'fail' ? 1 : 0;
    }
}
