<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendAuthorityValidationService;
use SaaSPlatform\CoreUtils\Frontend\DesignTokenGovernanceService;
use SaaSPlatform\CoreUtils\Frontend\CanonicalComponentRegistry;
use SaaSPlatform\CoreUtils\Frontend\FrontendDriftDetector;

class ValidateFrontendAuthorityCommand extends Command
{
    protected $signature = 'frontend:validate-authority
                            {--json : Output as JSON}
                            {--strict : Treat warnings as failures}
                            {--fail-on-warning : Exit 1 on any warning}';

    protected $description = 'Validate the canonical frontend authority system — tokens, components, drift, and parity';

    public function handle(
        FrontendAuthorityValidationService $validator,
        DesignTokenGovernanceService       $tokens,
        CanonicalComponentRegistry         $registry,
        FrontendDriftDetector              $drift
    ): int {
        $this->line('');
        $this->line('  <fg=blue;options=bold>Phase 10.0 — Frontend Authority Validation</>');
        $this->line('  ' . str_repeat('─', 52));

        $results    = $validator->validate();
        $tokenAudit = $tokens->auditTokenFile();
        $tokenScore = $tokens->tokenCoverageScore();
        $regReport  = $registry->report();
        $driftReport= $drift->detect();

        if ($this->option('json')) {
            $this->line(json_encode([
                'authority'  => $results,
                'tokens'     => $tokenAudit,
                'token_score'=> $tokenScore,
                'components' => $regReport,
                'drift'      => $driftReport,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->exitCode($results, $driftReport);
        }

        // ── Authority Checks ──────────────────────────────────────────────
        $this->line('');
        $this->info('  Authority Checks:');
        foreach ($results['checks'] as $key => $check) {
            $icon = match($check['status']) {
                'pass'    => '<fg=green>✓</>',
                'warning' => '<fg=yellow>⚠</>',
                'fail'    => '<fg=red>✗</>',
                default   => ' ',
            };
            $this->line("  {$icon}  {$check['message']}");
        }

        // ── Token Governance ──────────────────────────────────────────────
        $this->line('');
        $this->info('  Design Token Governance:');
        $this->line("  ├ Total tokens defined  : {$tokenAudit['total_tokens']}");
        $this->line("  ├ Dark mode ready        : " . ($tokenAudit['has_dark_mode'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->line("  ├ RTL support            : " . ($tokenAudit['has_rtl'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->line("  ├ Canonical keyframes    : " . ($tokenAudit['has_keyframes'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->line("  └ Backward compat aliases: " . ($tokenAudit['has_compat'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->line("    Token coverage score   : {$tokenScore}/100");

        // ── Component Registry ────────────────────────────────────────────
        $this->line('');
        $this->info('  Canonical Component Registry:');
        $this->line("  ├ Total components       : {$regReport['total']}");
        $this->line("  ├ Canonical (core-utils) : {$regReport['canonical']}");
        $this->line("  └ Module-registered      : {$regReport['non_canonical']}");

        // ── Drift Report ──────────────────────────────────────────────────
        $this->line('');
        $this->info('  Frontend Drift Report:');
        if ($driftReport['drift_free']) {
            $this->line('  <fg=green>✓  No frontend drift detected between saas-platform and customer-site</>');
        } else {
            foreach ($driftReport['drift_points'] as $key => $point) {
                $color = match($point['severity']) {
                    'CRITICAL' => 'red',
                    'HIGH'     => 'yellow',
                    default    => 'blue',
                };
                $this->line("  <fg={$color}>[{$point['severity']}]</> {$point['message']}");
            }
        }

        // ── Summary ───────────────────────────────────────────────────────
        $this->line('');
        $this->line('  ' . str_repeat('─', 52));
        $grade = $results['grade'];
        $score = $results['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
        $this->line("  <fg={$color};options=bold>Frontend Authority Score: {$score}/100 (Grade {$grade})</>");
        $this->line("  Checks: {$results['passed']} passed, {$results['warnings']} warnings, {$results['errors']} failed");
        $this->line('');

        return $this->exitCode($results, $driftReport);
    }

    private function exitCode(array $results, array $driftReport): int
    {
        if ($results['errors'] > 0 || count($driftReport['critical']) > 0) {
            return self::FAILURE;
        }
        if ($this->option('strict') || $this->option('fail-on-warning')) {
            return ($results['warnings'] > 0) ? self::FAILURE : self::SUCCESS;
        }
        return self::SUCCESS;
    }
}
