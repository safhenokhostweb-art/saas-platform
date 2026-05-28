<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\SecurityCenter;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityCenterManager;
use SaaSPlatform\CoreUtils\SecurityCenter\RuntimeTrustAnalyzer;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityIntegrityVerifier;

class SecurityCenterRuntimeStatusCommand extends Command
{
    protected $signature = 'security-center:runtime-status
        {--tenant=global : Tenant ID to check}
        {--json : Output as JSON}';

    protected $description = 'Show real-time Security Center runtime status including trust score, integrity state, and active alerts';

    public function handle(
        SecurityCenterManager $manager,
        RuntimeTrustAnalyzer $trustAnalyzer,
        SecurityIntegrityVerifier $integrityVerifier
    ): int {
        $tenantId = $this->option('tenant');
        $summary  = $manager->getDashboardSummary($tenantId);
        $trust    = $trustAnalyzer->getTrustReport($tenantId);
        $integrity= $integrityVerifier->getReport();

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary'   => $summary,
                'trust'     => $trust,
                'integrity' => $integrity,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Security Center — Runtime Status</>');
        $this->line('  ' . str_repeat('─', 50));
        $this->newLine();

        $sc = $summary['security_score'];
        $tc = $summary['trust_score'];
        $scColor = $sc >= 80 ? 'green' : ($sc >= 60 ? 'yellow' : 'red');
        $tcColor = $tc >= 80 ? 'green' : ($tc >= 60 ? 'yellow' : 'red');

        $this->line("  Security Score  : <fg={$scColor};options=bold>{$sc}/100 (Grade {$summary['security_grade']})</>");
        $this->line("  Trust Score     : <fg={$tcColor};options=bold>{$tc}/100 ({$summary['trust_level']})</>");
        $this->line("  Runtime Health  : <fg=green>{$summary['runtime_health']}</>");
        $this->line("  Integrity State : {$summary['runtime_integrity_state']}");
        $this->line("  Signed Release  : " . ($summary['signed_release'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->newLine();
        $this->line("  Installed Patches : {$summary['installed_patches']}");
        $this->line("  Pending Critical  : " . ($summary['pending_critical'] > 0 ? "<fg=red>{$summary['pending_critical']}</>" : '<fg=green>0</>'));
        $this->line("  Pending Total     : {$summary['pending_total']}");
        $this->line("  Patch Confidence  : {$summary['patch_confidence']}%");
        $this->newLine();
        $this->line("  Auto-Update     : " . ($summary['auto_update_enabled'] ? '<fg=green>Enabled</>' : '<fg=yellow>Disabled</>'));
        $this->line("  Rollback Ready  : " . ($summary['rollback_available'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>'));
        $this->line("  Last Update     : " . ($summary['last_security_update'] ?? '<fg=gray>Never</>'));
        $this->newLine();

        if ($summary['pending_critical'] > 0) {
            $this->warn("  ⚠ {$summary['pending_critical']} critical security patch(es) pending installation.");
        } else {
            $this->info("  ✓ Security Center is healthy.");
        }

        $this->newLine();
        return 0;
    }
}
