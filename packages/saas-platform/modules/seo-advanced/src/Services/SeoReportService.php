<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;

/**
 * Phase 18-D — Part 5: builds the same read-only report data for both the
 * Admin report page and the Customer (site-scoped) report page — the only
 * difference between the two is which site_id the caller is allowed to
 * pass in (see SeoAdvancedAdminController::report() vs
 * Site\SeoAdvancedCustomerReportController, which always uses its own
 * fixed single-tenant site).
 */
class SeoReportService
{
    public function build(int $siteId): array
    {
        $latestAudit = SeoAdvancedAudit::where('site_id', $siteId)->latest('audited_at')->first();

        $issueCounts = ['critical' => 0, 'warning' => 0, 'recommendation' => 0];
        foreach (optional($latestAudit)->issues ?? [] as $issue) {
            $severity = $issue['severity'] ?? 'recommendation';
            if (isset($issueCounts[$severity])) {
                $issueCounts[$severity]++;
            }
        }

        $scoreHistory = SeoAdvancedAudit::where('site_id', $siteId)
            ->orderBy('audited_at')
            ->limit(50)
            ->get(['score', 'audited_at'])
            ->map(fn ($audit) => [
                'date' => optional($audit->audited_at)->format('Y-m-d H:i'),
                'score' => $audit->score,
            ])
            ->values()
            ->all();

        $auditedPageCount = SeoAdvancedAudit::where('site_id', $siteId)->distinct('page_url')->count('page_url');

        $keywords = SeoAdvancedKeyword::where('site_id', $siteId)->orderBy('keyword')->get();

        return [
            'latest_audit' => $latestAudit,
            'issue_counts' => $issueCounts,
            'score_history' => $scoreHistory,
            'audited_page_count' => $auditedPageCount,
            'keywords' => $keywords,
            'keyword_count' => $keywords->count(),
        ];
    }
}
