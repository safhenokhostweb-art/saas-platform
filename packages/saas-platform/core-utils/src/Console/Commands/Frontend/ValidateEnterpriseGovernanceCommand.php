<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\EnterpriseFrontendGovernanceValidationService;

class ValidateEnterpriseGovernanceCommand extends Command
{
    protected $signature = 'frontend:validate-enterprise-governance
                            {--strict       : Fail if score below 90 instead of 80}
                            {--json         : Output as JSON}
                            {--fail-below=  : Fail if score below this value (0-100)}';

    protected $description = 'Master enterprise frontend governance validation — 12-dimension scoring across all modules and contexts';

    public function handle(EnterpriseFrontendGovernanceValidationService $service): int
    {
        if (! $this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>╔══════════════════════════════════════════════════════════════╗</>');
            $this->line('<fg=cyan;options=bold>║    Enterprise Frontend Governance Validation — Phase 10.1   ║</>');
            $this->line('<fg=cyan;options=bold>╚══════════════════════════════════════════════════════════════╝</>');
            $this->line('');
        }

        $strict    = (bool) $this->option('strict');
        $failBelow = $this->option('fail-below') !== null ? (int) $this->option('fail-below') : null;
        $result    = $service->validate($strict);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $this->exitCode($result, $failBelow);
        }

        // Score banner
        $scoreColor = $result['score'] >= 90 ? 'green' : ($result['score'] >= 75 ? 'yellow' : 'red');
        $this->line(sprintf(
            '<fg=%s;options=bold>  Score: %d/100 (Grade %s)</>',
            $scoreColor, $result['score'], $result['grade']
        ));
        $this->line('');

        // Dimension table
        $tableRows = [];
        foreach ($result['report'] as $dim) {
            $statusColor = match($dim['status']) {
                'PASS'     => 'green',
                'MEDIUM'   => 'blue',
                'HIGH'     => 'yellow',
                'CRITICAL' => 'red',
                default    => 'white',
            };
            $scoreDisp = $dim['score'] >= 90 ? "<fg=green>{$dim['score']}</>" : ($dim['score'] >= 70 ? "<fg=yellow>{$dim['score']}</>" : "<fg=red>{$dim['score']}</>");
            $tableRows[] = [
                str_replace('_', ' ', ucwords($dim['dimension'], '_')),
                $scoreDisp . '/100',
                "×{$dim['weight']}",
                "<fg={$statusColor}>{$dim['status']}</>",
                count($dim['issues']) > 0 ? implode('; ', array_slice($dim['issues'], 0, 1)) . (count($dim['issues']) > 1 ? '...' : '') : '—',
            ];
        }

        $this->table(
            ['Dimension', 'Score', 'Weight', 'Status', 'Top Issue'],
            $tableRows
        );

        // Issues by dimension
        foreach ($result['report'] as $dim) {
            if (empty($dim['issues'])) continue;
            $this->line('');
            $key   = str_replace('_', ' ', $dim['dimension']);
            $color = $dim['score'] < 70 ? 'red' : 'yellow';
            $this->line("<fg={$color};options=bold>── {$key} ({$dim['score']}/100) ──</>");
            foreach ($dim['issues'] as $issue) {
                $this->line("   <fg={$color}>•</> {$issue}");
            }
        }

        $this->line('');
        if ($result['passed']) {
            $this->line('<fg=green;options=bold>✓ Enterprise frontend governance validation PASSED!</>');
        } else {
            $this->line('<fg=red;options=bold>✗ Enterprise frontend governance validation FAILED — remediation required.</>');
        }
        $this->line('<options=bold>' . $result['summary'] . '</>');
        $this->line('');

        return $this->exitCode($result, $failBelow);
    }

    private function exitCode(array $result, ?int $failBelow): int
    {
        if ($failBelow !== null) {
            return $result['score'] >= $failBelow ? self::SUCCESS : self::FAILURE;
        }
        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
