<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\AI;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\CoreUtils\AI\Governance\AiCostEstimator;
use SaaSPlatform\CoreUtils\AI\Governance\AiRequestBudgetManager;

class AiQuotaReportCommand extends Command
{
    protected $signature = 'ai:quota-report
        {tenant? : Tenant ID for per-tenant report. Omit for system overview.}
        {--days=7 : Number of days for historical data}
        {--json : Output as JSON}';

    protected $description = 'Show AI usage quota and cost report';

    public function handle(
        TenantAiQuotaManager  $quotaManager,
        AiCostEstimator       $costEstimator,
        AiRequestBudgetManager $budgetManager,
    ): int {
        $tenantId = $this->argument('tenant') ?? '__system__';
        $days     = (int) $this->option('days');
        $jsonMode = (bool) $this->option('json');

        if ($jsonMode) {
            $data = [
                'tenant_id'    => $tenantId,
                'days'         => $days,
                'quota_today'  => $tenantId !== '__system__' ? $quotaManager->check($tenantId) : null,
                'usage_history'=> $tenantId !== '__system__' ? $quotaManager->getUsageReport($tenantId, $days) : $quotaManager->getSystemUsageReport($days),
                'cost_history' => $costEstimator->getTenantCost($tenantId, $days),
                'total_cost'   => $costEstimator->getTenantTotalCost($tenantId, $days),
                'budget'       => $tenantId !== '__system__' ? $budgetManager->getBudgetStatus($tenantId) : null,
            ];
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->newLine();
        $header = $tenantId === '__system__' ? 'AI Quota Report — System' : "AI Quota Report — Tenant: {$tenantId}";
        $this->line("<fg=bright-blue;options=bold>{$header}</>");
        $this->line(str_repeat('─', 55));

        if ($tenantId !== '__system__') {
            $quota = $quotaManager->check($tenantId);
            $this->line('<options=bold>Today\'s Quota</>');
            $this->line("  Limit:     {$quota['limit']}");
            $this->line("  Used:      {$quota['used']}");
            $this->line("  Remaining: {$quota['remaining']}");

            $budget = $budgetManager->getBudgetStatus($tenantId);
            if ($budget['has_budget']) {
                $this->newLine();
                $this->line('<options=bold>Budget Status</>');
                $this->line("  Daily budget: \${$budget['budget']}");
                $this->line("  Spent:        \${$budget['spent']}");
                $this->line("  Remaining:    \${$budget['remaining']}");
                $color = match($budget['status']) { 'ok' => 'green', 'warning' => 'yellow', 'exhausted' => 'red', default => 'gray' };
                $this->line("  Status: <fg={$color}>{$budget['status']}</>");
            }
        }

        $history  = $tenantId === '__system__'
            ? $quotaManager->getSystemUsageReport($days)
            : $quotaManager->getUsageReport($tenantId, $days);

        $this->newLine();
        $this->line("<options=bold>Usage History ({$days}d)</>");
        foreach ($history as $date => $count) {
            $bar = str_repeat('█', min(30, $count));
            $this->line("  {$date}: {$bar} {$count}");
        }

        $totalCost = $costEstimator->getTenantTotalCost($tenantId, $days);
        $this->newLine();
        $this->line("<options=bold>Estimated Cost ({$days}d): <fg=cyan>\${$totalCost}</></>");

        $this->newLine();
        return Command::SUCCESS;
    }
}
