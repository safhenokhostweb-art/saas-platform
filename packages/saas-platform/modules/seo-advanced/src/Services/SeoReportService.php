<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\SearchConsolePerformanceProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;

/**
 * Phase 18-D — Part 5 / Phase 18-E — Part 9: builds the same read-only
 * report data for both the Admin report page and the Customer
 * (site-scoped) report page. Phase 18-E adds Google Performance (via
 * SearchConsolePerformanceProviderInterface — null when not connected,
 * never fetched eagerly if disconnected) and AI Recommendations (via
 * AiSeoAssistantService — empty when no provider is configured), on top
 * of the existing Score/Issues/Keywords/History.
 */
class SeoReportService
{
    public function __construct(
        private readonly SearchConsolePerformanceProviderInterface $searchConsole,
    ) {}

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

        $googlePerformance = null;
        if ($this->searchConsole->isConnected($siteId)) {
            $googlePerformance = $this->searchConsole->getPerformance($siteId, now()->subDays(30)->toDateString(), now()->subDays(2)->toDateString());
        }

        return [
            'latest_audit' => $latestAudit,
            'issue_counts' => $issueCounts,
            'score_history' => $scoreHistory,
            'audited_page_count' => $auditedPageCount,
            'keywords' => $keywords,
            'keyword_count' => $keywords->count(),
            'google_performance' => $googlePerformance,
            'recommendations' => optional($latestAudit)->issues
                ? collect($latestAudit->issues)->pluck('suggested_fix')->filter()->unique()->values()->all()
                : [],
        ];
    }
}
