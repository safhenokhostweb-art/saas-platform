<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendCrossRepositoryGovernanceValidator;

class ValidateCrossRepositoryGovernanceCommand extends Command
{
    protected $signature = 'frontend:validate-cross-repository-governance
                            {--json            : Output results as JSON}
                            {--strict          : Require score >= 95 to pass}
                            {--fail-on-warning : Treat warnings as failures}';

    protected $description = 'Validate that saas-platform and customer-site maintain identical frontend governance posture';

    public function handle(FrontendCrossRepositoryGovernanceValidator $validator): int
    {
        $strict         = (bool) $this->option('strict');
        $failOnWarning  = (bool) $this->option('fail-on-warning');
        $json           = (bool) $this->option('json');

        $result = $validator->validate($strict, $failOnWarning);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('  <options=bold>Cross-Repository Frontend Governance Validation</>');
        $this->line('  ──────────────────────────────────────────────────────');
        $this->newLine();

        $score = $result['score'];
        $grade = $result['grade'];
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');

        $this->line("  <fg={$color};options=bold>Score: {$score}/100 (Grade {$grade})</>");
        $this->newLine();

        // Dimension table
        $rows = [];
        foreach ($result['dimensions'] as $key => $dim) {
            $statusColor = match($dim['status']) {
                'PASS'     => 'green',
                'WARNING'  => 'yellow',
                'MEDIUM'   => 'yellow',
                'HIGH'     => 'red',
                'CRITICAL' => 'red',
                default    => 'white',
            };
            $rows[] = [
                str_replace('_', ' ', ucwords($key, '_')),
                "<fg={$statusColor}>{$dim['score']}/100</>",
                "<fg={$statusColor}>{$dim['status']}</>",
                implode('; ', array_slice($dim['issues'], 0, 1)) ?: '—',
            ];
        }

        $this->table(
            ['Dimension', 'Score', 'Status', 'Top Issue'],
            $rows
        );

        // Violations
        if (!empty($result['violations'])) {
            $this->newLine();
            $this->warn('  Cross-Repository Violations:');
            foreach ($result['violations'] as $v) {
                $icon = match($v['severity']) {
                    'CRITICAL', 'HIGH' => '✗',
                    'WARNING'           => '⚠',
                    default             => '→',
                };
                $this->line("  {$icon} [{$v['dimension']}] {$v['message']}");
            }
        }

        // Recommendations
        if (!empty($result['recommendations'])) {
            $this->newLine();
            $this->line('  <fg=cyan>Recommendations:</>');
            foreach ($result['recommendations'] as $i => $rec) {
                $this->line('  ' . ($i + 1) . '. ' . $rec);
            }
        }

        $this->newLine();
        $this->line('  ' . $result['summary']);
        $this->newLine();

        if (!$result['passed']) {
            $this->error('  Cross-repository governance validation FAILED. Fix violations before merging.');
            return self::FAILURE;
        }

        $this->line('  <fg=green>✓ Cross-repository governance validation passed.</>');
        return self::SUCCESS;
    }
}
