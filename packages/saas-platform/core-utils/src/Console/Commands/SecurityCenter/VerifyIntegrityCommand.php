<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\SecurityCenter;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityIntegrityVerifier;
use SaaSPlatform\CoreUtils\SecurityCenter\TrustedReleaseValidator;
use SaaSPlatform\CoreUtils\SecurityCenter\RuntimeTrustAnalyzer;

class VerifyIntegrityCommand extends Command
{
    protected $signature = 'security-center:verify-integrity
        {--fresh : Force a fresh verification (bypass cache)}
        {--json : Output as JSON}
        {--strict : Fail if any check does not pass}
        {--fail-on-warning : Treat warnings as failures}';

    protected $description = 'Run full platform integrity verification — checks signatures, hashes, trust chain, and runtime state';

    public function handle(
        SecurityIntegrityVerifier $integrityVerifier,
        RuntimeTrustAnalyzer $trustAnalyzer
    ): int {
        if ($this->option('fresh')) {
            $integrityVerifier->invalidateCache();
        }

        $report = $integrityVerifier->runVerification();
        $trust  = $trustAnalyzer->getTrustReport('global');

        if ($this->option('json')) {
            $this->line(json_encode([
                'integrity' => $report,
                'trust'     => $trust,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($report['verified'] && $trust['overall_score'] >= 80) ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Security Center — Integrity Verification</>');
        $this->line('  ' . str_repeat('─', 50));
        $this->newLine();

        foreach ($report['checks'] as $check => $passed) {
            $icon  = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $label = str_pad(str_replace('_', ' ', ucwords($check, '_')), 30);
            $this->line("  {$icon}  {$label} " . ($passed ? 'Verified' : 'FAILED'));
        }

        $this->newLine();
        $intColor = $report['score'] >= 90 ? 'green' : ($report['score'] >= 70 ? 'yellow' : 'red');
        $this->line("  Integrity Score  : <fg={$intColor};options=bold>{$report['score']}/100</>");
        $this->line("  State            : {$report['state']}");
        $this->line("  Signed Release   : " . ($report['signed'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->line("  Tamper Detected  : " . (! $report['verified'] ? '<fg=red>YES</>' : '<fg=green>No</>'));
        $this->newLine();

        $trustColor = $trust['overall_score'] >= 80 ? 'green' : ($trust['overall_score'] >= 60 ? 'yellow' : 'red');
        $this->line("  Trust Score      : <fg={$trustColor};options=bold>{$trust['overall_score']}/100 ({$trust['trust_level']})</>");

        foreach ($trust['dimensions'] as $dim) {
            $sc    = $dim['score'];
            $col   = $sc >= 80 ? 'green' : ($sc >= 60 ? 'yellow' : 'red');
            $label = str_pad(str_replace('_', ' ', ucwords($dim['dimension'], '_')), 30);
            $this->line("      <fg={$col}>{$label} {$sc}/100</>");
        }

        $this->newLine();

        $failed      = ! $report['verified'];
        $hasWarning  = $report['score'] < 90 || $trust['overall_score'] < 90;
        $strict      = $this->option('strict') || $this->option('fail-on-warning');

        if ($failed) {
            $this->error('  ✗ Integrity verification FAILED — platform may be compromised.');
            return 1;
        }

        if ($hasWarning && $strict) {
            $this->warn('  ⚠ Integrity verification completed with warnings (treated as failure by --strict).');
            return 1;
        }

        if ($hasWarning) {
            $this->warn('  ⚠ Integrity verified with warnings. Review trust dimensions above.');
        } else {
            $this->info('  ✓ Full integrity verification passed. Platform is clean and trusted.');
        }

        $this->newLine();
        return 0;
    }
}
