<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsFrontendGovernanceValidationService;

class FormsFrontendGovernanceCommand extends Command
{
    protected $signature   = 'forms:validate-frontend-governance {--json : Output as JSON}';
    protected $description = 'Validate Forms module frontend governance rules';

    public function handle(FormsFrontendGovernanceValidationService $validator): int
    {
        $this->line('');
        $this->line('<fg=blue;options=bold>Forms Module — Frontend Governance Validation</>');
        $this->line(str_repeat('─', 55));

        $result = $validator->validate();

        $this->line('');

        // Passed checks
        foreach ($result['passed'] as $check) {
            $this->line("  <fg=green>✓</> {$check}");
        }

        // Warnings
        foreach ($result['warnings'] as $warning) {
            $this->line('');
            $this->line("  <fg=yellow>⚠</> {$warning['rule']}");
            $this->line("    {$warning['message']}");
        }

        // Violations
        foreach ($result['violations'] as $violation) {
            $this->line('');
            $this->line("  <fg=red>✗</> {$violation['rule']}");
            $this->line("    {$violation['message']}");
            if (isset($violation['path'])) {
                $this->line("    Path: {$violation['path']}");
            }
        }

        $this->line('');
        $this->line(str_repeat('─', 55));

        $statusColor  = $result['compliant'] ? 'green' : 'red';
        $statusLabel  = $result['compliant'] ? '✅ COMPLIANT' : '❌ VIOLATIONS FOUND';
        $violationCount = count($result['violations']);
        $warningCount   = count($result['warnings']);
        $passedCount    = count($result['passed']);

        $this->line("<fg={$statusColor};options=bold>{$statusLabel}</>");
        $this->line("  Score  : <options=bold>{$result['score']}/100</>");
        $this->line("  Passed : {$passedCount}");
        $this->line("  Warnings : {$warningCount}");
        $this->line("  Violations : {$violationCount}");
        $this->line('');

        if ($this->option('json')) {
            $this->output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $result['compliant'] ? self::SUCCESS : self::FAILURE;
    }
}
