<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\AI;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\AI\Governance\AiGovernanceValidationService;

class ValidateAiGovernanceCommand extends Command
{
    protected $signature = 'ai:validate-governance
        {--json : Output results as JSON}
        {--fail-below=80 : Exit with code 1 if score is below this threshold}
        {--strict : Treat warnings as failures}';

    protected $description = 'Validate AI governance configuration, security, and operational health';

    public function handle(AiGovernanceValidationService $service): int
    {
        $result     = $service->validate();
        $threshold  = (int) $this->option('fail-below');
        $strict     = (bool) $this->option('strict');
        $jsonMode   = (bool) $this->option('json');

        if ($jsonMode) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['score'] >= $threshold ? Command::SUCCESS : Command::FAILURE;
        }

        $score   = $result['score'];
        $grade   = $result['grade'];
        $passed  = $result['passed'];
        $total   = $result['total'];

        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line("<fg=bright-blue;options=bold>AI Governance Validation</>");
        $this->line(str_repeat('─', 50));

        foreach ($result['checks'] as $check) {
            $status = $check['status'];
            if ($strict && $status === 'warn') $status = 'fail';
            $icon = match($status) {
                'pass' => '<fg=green>✓</>',
                'warn' => '<fg=yellow>⚠</>',
                'fail' => '<fg=red>✗</>',
                default => '?',
            };
            $this->line(" {$icon}  {$check['message']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 50));
        $this->line("<fg={$color};options=bold>Score: {$score}/100 (Grade {$grade})</> — {$passed}/{$total} checks passed");
        $this->newLine();

        if ($score >= 90) {
            $this->info('AI Governance: Enterprise-Ready ✓');
        } elseif ($score >= 70) {
            $this->warn('AI Governance: Needs Attention — address warnings before production');
        } else {
            $this->error('AI Governance: Not Ready — critical issues must be resolved');
        }

        return $result['score'] >= $threshold ? Command::SUCCESS : Command::FAILURE;
    }
}
