<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Enterprise;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Enterprise\EnterpriseRuntimeGovernanceValidator;

class ValidateEnterpriseGovernanceCommand extends Command
{
    protected $signature = 'enterprise:validate
                            {--json : Output as JSON}
                            {--fail-below=80 : Exit non-zero if score below threshold}';

    protected $description = 'Run enterprise runtime governance validation — Phase 9.4 maturity check';

    public function handle(EnterpriseRuntimeGovernanceValidator $validator): int
    {
        $this->info('🏢 Running enterprise runtime governance validation...');
        $this->newLine();

        $result = $validator->validate();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['score'] >= (int) $this->option('fail-below') ? self::SUCCESS : self::FAILURE;
        }

        $score = $result['score'];
        $grade = $result['grade'];
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');

        $this->line("  <fg=white;options=bold>Enterprise Maturity Score:</> <fg={$color};options=bold>{$score}/100</>");
        $this->line("  <fg={$color};options=bold>Grade: {$grade}</>");
        $this->newLine();

        $rows = [];
        foreach ($result['checks'] as $check) {
            $icon  = $check['status'] === 'pass' ? '✓' : '✗';
            $color = $check['status'] === 'pass' ? 'green' : 'red';
            $rows[] = [
                "<fg={$color}>{$icon}</>",
                $check['check'],
                $check['description'],
                $check['error'] ?? '',
            ];
        }
        $this->table(['', 'Check', 'Description', 'Error'], $rows);

        $this->newLine();
        $this->line("  Passed: {$result['passed']}/{$result['total']} checks");
        $this->line("  Validated at: {$result['validated_at']}");
        $this->newLine();

        $threshold = (int) $this->option('fail-below');
        if ($score >= $threshold) {
            $this->info("  ✅ Enterprise governance validation PASSED (score {$score} ≥ {$threshold})");
            return self::SUCCESS;
        } else {
            $this->error("  ❌ Enterprise governance validation FAILED (score {$score} < {$threshold})");
            return self::FAILURE;
        }
    }
}
