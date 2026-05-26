<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Deployment;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\DeploymentGovernanceValidationService;

class ValidateGovernanceCommand extends Command
{
    protected $signature = 'deployment:validate-governance
                            {--strict          : Score must be 100%}
                            {--json            : Output as JSON}
                            {--fail-on-warning : Exit 1 if overall score < 80}';

    protected $description = 'Validate all deployment governance systems (Phase 8.2)';

    public function handle(DeploymentGovernanceValidationService $validator): int
    {
        $strict = $this->option('strict');
        $result = $validator->run($strict);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>DEPLOYMENT GOVERNANCE VALIDATION — PHASE 8.2</>');
        $this->line('  ──────────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['dimensions'] as $name => $dim) {
            $icon  = $dim['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $score = str_pad((string) $dim['score'] . '%', 6);
            $label = str_pad($name, 26);
            $this->line("  [{$icon}] {$score} {$label} {$dim['detail']}");
        }

        $this->newLine();
        $this->line('  ────────────────────────────────────────────────────');

        $scoreBlock = "Score: {$result['score']}%  Grade: {$result['grade']}";
        if ($result['passed']) {
            $this->line("  <fg=green;options=bold>✓ PASSED — {$scoreBlock}</>");
        } else {
            $this->line("  <fg=red;options=bold>✗ FAILED — {$scoreBlock}</>");
        }

        $this->newLine();
        $this->line('  <fg=cyan>Dimension Scores:</>');
        foreach ($result['scores'] as $dim => $score) {
            $grade = $score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 70 ? 'C' : 'D'));
            $color = $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red');
            $label = str_pad(str_replace('_', ' ', $dim), 28);
            $this->line("     <fg={$color}>{$score}%</> {$label}");
        }

        $this->newLine();

        if (!$result['passed'] && ($this->option('fail-on-warning') || $strict)) {
            return 1;
        }

        return 0;
    }
}
