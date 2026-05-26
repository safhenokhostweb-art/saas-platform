<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Telemetry;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Telemetry\ObservabilityGovernanceValidationService;

class ValidateObservabilityCommand extends Command
{
    protected $signature   = 'observability:validate-governance
                                {--strict : Fail on warnings, not just failures}
                                {--json : Output as JSON}
                                {--fail-on-warning : Exit 1 if any warnings exist}';

    protected $description = 'Validate observability and telemetry governance compliance';

    public function handle(ObservabilityGovernanceValidationService $service): int
    {
        $result = $service->validate();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $this->resolveExitCode($result);
        }

        $this->info("Observability Governance Validation");
        $this->line('─────────────────────────────────────────');
        $this->line("Score : {$result['overall_score']}/100 (Grade {$result['grade']})");
        $this->line('');

        $this->info('Dimension Scores:');
        foreach ($result['dimensions'] as $dim => $score) {
            $icon  = $score >= 80 ? '✓' : ($score >= 50 ? '~' : '✗');
            $label = str_pad(str_replace('_', ' ', ucwords($dim, '_')), 30);
            $this->line("  {$icon} {$label} {$score}/100");
        }

        $this->line('');
        $this->info('Checks:');
        foreach ($result['checks'] as $check) {
            $icon = match ($check['status']) {
                'pass'    => '<fg=green>PASS</>',
                'warning' => '<fg=yellow>WARN</>',
                'fail'    => '<fg=red>FAIL</>',
                default   => '    ',
            };
            $this->line("  [{$icon}] {$check['message']}");
        }

        if (!empty($result['failures'])) {
            $this->line('');
            $this->error('Failures (' . count($result['failures']) . '):');
            foreach ($result['failures'] as $f) {
                $this->line("  • {$f}");
            }
        }

        if (!empty($result['warnings'])) {
            $this->line('');
            $this->warn('Warnings (' . count($result['warnings']) . '):');
            foreach ($result['warnings'] as $w) {
                $this->line("  • {$w}");
            }
        }

        return $this->resolveExitCode($result);
    }

    private function resolveExitCode(array $result): int
    {
        if (!empty($result['failures'])) {
            return self::FAILURE;
        }

        if (($this->option('strict') || $this->option('fail-on-warning')) && !empty($result['warnings'])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
