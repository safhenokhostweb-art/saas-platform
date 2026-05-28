<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\FrontendCanonicalParityEnforcer;

class ValidateCanonicalParityCommand extends Command
{
    protected $signature   = 'frontend:validate-canonical-parity {--json} {--strict} {--fail-on-warning}';
    protected $description = 'Audit frontend canonical parity between saas-platform and customer-site.';

    public function handle(FrontendCanonicalParityEnforcer $enforcer): int
    {
        $result = $enforcer->audit();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $this->newLine();
            $this->line('  <fg=cyan>Frontend Canonical Parity Audit</>');
            $score = $result['score'];
            $grade = $result['grade'];
            $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
            $this->line("  Score: <fg={$color}>{$score}/100 ({$grade})</>");
            $this->line('  Parity: '.($result['parity'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));
            $this->newLine();

            foreach ($result['checks'] as $check) {
                $icon = $check['pass'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $this->line("  {$icon} {$check['check']}");
            }

            if (! empty($result['violations'])) {
                $this->newLine();
                $this->warn('  Violations:');
                foreach ($result['violations'] as $v) {
                    $this->line("    [{$v['severity']}] {$v['message']}");
                }
            }
            $this->newLine();
        }

        $hasViolations = ! empty($result['violations']);
        $criticals = array_filter($result['violations'], fn($v) => ($v['severity'] ?? '') === 'critical');

        if ($this->option('strict') && ($hasViolations || $this->option('fail-on-warning'))) {
            return self::FAILURE;
        }
        return ! empty($criticals) ? self::FAILURE : self::SUCCESS;
    }
}
