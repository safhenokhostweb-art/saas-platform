<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\FrontendCanonicalParityEnforcer;

class EnforceParityCommand extends Command
{
    protected $signature   = 'frontend:enforce-parity {--json} {--dry-run}';
    protected $description = 'Enforce canonical frontend parity — detect and report all divergences.';

    public function handle(FrontendCanonicalParityEnforcer $enforcer): int
    {
        $result = $enforcer->enforce();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $result['parity'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan>Frontend Canonical Parity Enforcement</>');
        $score = $result['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
        $this->line("  Score: <fg={$color}>{$score}/100 ({$result['grade']})</>");
        $this->newLine();

        if (! empty($result['enforcement_actions'])) {
            $this->warn('  Actions Required:');
            foreach ($result['enforcement_actions'] as $action) {
                $this->line("    [{$action['action']}] {$action['path']}");
            }
            $this->newLine();
        }

        if (empty($result['violations'])) {
            $this->info('  ✓ Canonical parity is fully enforced.');
        } else {
            $this->error('  ✗ Parity violations detected — see enforcement_actions above.');
        }

        return $result['parity'] ? self::SUCCESS : self::FAILURE;
    }
}
