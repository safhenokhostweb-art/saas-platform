<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Validation\MobileProductionReadinessService;

class ValidateProductionReadinessCommand extends Command
{
    protected $signature = 'mobile:validate-production-readiness
        {--json             : Output as JSON}
        {--strict           : Treat any score below 80 per dimension as failure}
        {--fail-on-warning  : Exit 1 if overall score is below 90}
        {--tenant=          : Assess for specific tenant}';

    protected $description = 'Run 10-dimension mobile production readiness assessment';

    public function handle(MobileProductionReadinessService $readiness): int
    {
        $tenantId = $this->option('tenant') ?? 'platform';
        $strict   = (bool) $this->option('strict');
        $failWarn = (bool) $this->option('fail-on-warning');

        $report = $readiness->assess($tenantId);
        $score  = $report['score'];
        $color  = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($failWarn && $score < 90) return self::FAILURE;
            return $score >= 70 ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Production Readiness Assessment</>');
        $this->line(str_repeat('─', 60));

        $maxLen = max(array_map(fn ($d) => strlen($d['dimension']), $report['dimensions']));
        foreach ($report['dimensions'] as $dim) {
            $label    = str_pad($dim['dimension'], $maxLen);
            $dimScore = $dim['score'];
            $dimColor = $dimScore >= 90 ? 'green' : ($dimScore >= 70 ? 'yellow' : 'red');
            $mark     = $dimScore >= 80 ? '<fg=green>✓</>' : ($dimScore >= 60 ? '<fg=yellow>⚠</>' : '<fg=red>✗</>');
            $this->line("  {$mark}  {$label}  <fg={$dimColor};options=bold>{$dimScore}/100</>  {$dim['description']}");
        }

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->line("<fg={$color};options=bold>Overall Production Readiness: {$score}/100 (Grade {$report['grade']})</>");

        if (! empty($report['blockers'])) {
            $this->newLine();
            $this->warn('Blockers before production deployment:');
            foreach ($report['blockers'] as $blocker) {
                $this->line("  <fg=red>✗</>  [{$blocker['dimension']}] {$blocker['description']} (score: {$blocker['score']}/100)");
            }
        } else {
            $this->newLine();
            $this->info('All dimensions meet production threshold ✓');
        }

        $this->newLine();
        if ($failWarn && $score < 90) return self::FAILURE;
        return $score >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
