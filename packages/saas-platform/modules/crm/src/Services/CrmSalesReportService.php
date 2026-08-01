<?php

namespace SaaSPlatform\Modules\Crm\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;

/**
 * Phase 20 — Part 4: Sales Reports. Deliberately separate from
 * CrmOpportunityController::forecast() (Phase 19 — Part 5, which is a
 * single-pipeline "what's currently in flight" view) — this service
 * reports on closed/historical performance across a site, optionally
 * scoped to one pipeline.
 */
class CrmSalesReportService
{
    public function build(int $siteId, ?int $pipelineId = null, int $periodMonths = 6): array
    {
        $opportunities = CrmOpportunity::where('site_id', $siteId)
            ->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId));

        $wonCount = (clone $opportunities)->where('status', CrmOpportunity::STATUS_WON)->count();
        $lostCount = (clone $opportunities)->where('status', CrmOpportunity::STATUS_LOST)->count();
        $closedCount = $wonCount + $lostCount;

        $openOpportunities = (clone $opportunities)->open()->active()->get();

        $leads = CrmLead::where('site_id', $siteId);
        $leadCount = (clone $leads)->count();
        $convertedLeadCount = (clone $leads)->where('status', CrmLead::STATUS_CONVERTED)->count();

        return [
            'win_rate' => $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : 0.0,
            'lost_rate' => $closedCount > 0 ? round(($lostCount / $closedCount) * 100, 1) : 0.0,
            'conversion_rate' => $leadCount > 0 ? round(($convertedLeadCount / $leadCount) * 100, 1) : 0.0,
            'pipeline_value' => $openOpportunities->sum('amount'),
            'revenue_forecast' => $openOpportunities->sum(fn (CrmOpportunity $o) => $o->weightedAmount()),
            'won_count' => $wonCount,
            'lost_count' => $lostCount,
            // PDO returns aggregate SUM() as a string, not an int — cast explicitly.
            'won_amount' => (int) (clone $opportunities)->where('status', CrmOpportunity::STATUS_WON)->sum('amount'),
            'sales_by_stage' => $this->salesByStage($siteId, $pipelineId, $openOpportunities),
            'sales_by_period' => $this->salesByPeriod($siteId, $pipelineId, $periodMonths),
        ];
    }

    private function salesByStage(int $siteId, ?int $pipelineId, Collection $openOpportunities): Collection
    {
        $pipeline = $pipelineId
            ? CrmPipeline::where('site_id', $siteId)->with('stages')->find($pipelineId)
            : CrmPipeline::where('site_id', $siteId)->where('is_default', true)->with('stages')->first();

        if (!$pipeline) {
            return collect();
        }

        return $pipeline->stages->map(function ($stage) use ($openOpportunities) {
            $inStage = $openOpportunities->where('stage_id', $stage->id);

            return ['stage' => $stage, 'count' => $inStage->count(), 'amount' => $inStage->sum('amount')];
        });
    }

    /** @return array<int, array{period: string, won_amount: int, won_count: int}> */
    private function salesByPeriod(int $siteId, ?int $pipelineId, int $months): array
    {
        $rows = CrmOpportunity::where('site_id', $siteId)
            ->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->where('status', CrmOpportunity::STATUS_WON)
            ->where('closed_at', '>=', now()->subMonths($months)->startOfMonth())
            ->get(['amount', 'closed_at']);

        $byPeriod = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $byPeriod[$key] = ['period' => $key, 'won_amount' => 0, 'won_count' => 0];
        }

        foreach ($rows as $row) {
            $key = $row->closed_at->format('Y-m');
            if (isset($byPeriod[$key])) {
                $byPeriod[$key]['won_amount'] += $row->amount;
                $byPeriod[$key]['won_count']++;
            }
        }

        return array_values($byPeriod);
    }
}
