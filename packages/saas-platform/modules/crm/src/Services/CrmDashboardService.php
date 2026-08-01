<?php

namespace SaaSPlatform\Modules\Crm\Services;

use SaaSPlatform\Modules\Crm\Models\CrmActivity;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmTask;

/**
 * Phase 20 — Part 2: builds the same dashboard data for both the Admin
 * dashboard and the Customer (site-scoped) dashboard — the only
 * difference between the two is which site_id the caller is allowed to
 * pass in (see CrmDashboardController vs Site\CrmCustomerDashboardController,
 * which always uses the authenticated user's own site). Mirrors
 * SeoReportService's shared-builder pattern (Phase 18-D).
 */
class CrmDashboardService
{
    public function build(int $siteId): array
    {
        $leadCount = CrmLead::where('site_id', $siteId)->active()->where('status', '!=', CrmLead::STATUS_CONVERTED)->count();

        $customerCount = CrmContact::where('site_id', $siteId)->where('lifecycle_stage', CrmContact::STAGE_CUSTOMER)->count()
            + CrmCompany::where('site_id', $siteId)->where('lifecycle_stage', CrmCompany::STAGE_CUSTOMER)->count();

        $openOpportunities = CrmOpportunity::where('site_id', $siteId)->open()->active();

        return [
            'lead_count' => $leadCount,
            'customer_count' => $customerCount,
            'opportunity_count' => (clone $openOpportunities)->count(),
            // PDO returns aggregate SUM() as a string, not an int — cast explicitly.
            'pipeline_value' => (int) (clone $openOpportunities)->sum('amount'),
            'won_count' => CrmOpportunity::where('site_id', $siteId)->where('status', CrmOpportunity::STATUS_WON)->count(),
            'lost_count' => CrmOpportunity::where('site_id', $siteId)->where('status', CrmOpportunity::STATUS_LOST)->count(),
            'today_tasks' => CrmTask::where('site_id', $siteId)->pending()
                ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
                ->orderBy('due_at')
                ->get(),
            'recent_activities' => CrmActivity::where('site_id', $siteId)->latest('occurred_at')->limit(10)->get(),
        ];
    }
}
