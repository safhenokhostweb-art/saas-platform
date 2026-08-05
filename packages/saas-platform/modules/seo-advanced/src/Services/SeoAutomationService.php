<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeywordPosition;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

/**
 * Phase 18-E — Part 8: scheduled checks (rank drop, sitemap/technical
 * errors, score decrease) — dispatches via
 * SiteNotificationDispatcherInterface, the SAME generic extension point
 * CRM's task-reminder automation already uses (Phase 20 — Part 6/7),
 * never a parallel notification mechanism. A no-op (returns zero counts)
 * when nothing has bound a real dispatcher, matching that interface's
 * own documented contract.
 */
class SeoAutomationService
{
    private const RANK_DROP_THRESHOLD = 5; // positions
    private const SCORE_DROP_THRESHOLD = 10; // points

    public function __construct(
        private readonly SeoAuditor $auditor,
    ) {}

    /** @return array{rank_drops: int, sitemap_errors: int, technical_errors: int, score_drops: int} */
    public function runForSite(int $siteId): array
    {
        $counts = ['rank_drops' => 0, 'sitemap_errors' => 0, 'technical_errors' => 0, 'score_drops' => 0];

        if (!app()->bound(SiteNotificationDispatcherInterface::class)) {
            return $counts;
        }

        $dispatcher = app(SiteNotificationDispatcherInterface::class);

        $counts['rank_drops'] = $this->checkRankDrops($siteId, $dispatcher);
        [$counts['sitemap_errors'], $counts['technical_errors']] = $this->checkTechnicalHealth($siteId, $dispatcher);
        $counts['score_drops'] = $this->checkScoreDrops($siteId, $dispatcher);

        return $counts;
    }

    private function checkRankDrops(int $siteId, SiteNotificationDispatcherInterface $dispatcher): int
    {
        $notified = 0;

        SeoAdvancedKeyword::where('site_id', $siteId)->where('status', SeoAdvancedKeyword::STATUS_ACTIVE)->each(function (SeoAdvancedKeyword $keyword) use ($dispatcher, &$notified) {
            $recent = SeoAdvancedKeywordPosition::where('keyword_id', $keyword->id)->orderByDesc('recorded_at')->limit(2)->get();
            if ($recent->count() < 2) {
                return;
            }

            [$latest, $previous] = [$recent[0], $recent[1]];
            if ($latest->position === null || $previous->position === null) {
                return;
            }

            if ($latest->position - $previous->position >= self::RANK_DROP_THRESHOLD) {
                $dispatcher->notifySiteAdmin(
                    $keyword->site_id,
                    'افت رتبه کلمه کلیدی',
                    "رتبه «{$keyword->keyword}» از {$previous->position} به {$latest->position} افت کرد."
                );
                $notified++;
            }
        });

        return $notified;
    }

    /** @return array{0: int, 1: int} [sitemap_errors, technical_errors] */
    private function checkTechnicalHealth(int $siteId, SiteNotificationDispatcherInterface $dispatcher): array
    {
        $technical = $this->auditor->technicalChecks($siteId);
        $sitemapErrors = 0;
        $technicalErrors = 0;

        if (!($technical['sitemap_available'] ?? true)) {
            $dispatcher->notifySiteAdmin($siteId, 'خطای Sitemap', 'نقشه سایت این سایت در دسترس نیست.');
            $sitemapErrors++;
        }

        foreach (['robots_configured', 'schema_configured'] as $check) {
            if (!($technical[$check] ?? true)) {
                $dispatcher->notifySiteAdmin($siteId, 'خطای Technical SEO', "بررسی فنی «{$check}» ناموفق بود.");
                $technicalErrors++;
            }
        }

        return [$sitemapErrors, $technicalErrors];
    }

    private function checkScoreDrops(int $siteId, SiteNotificationDispatcherInterface $dispatcher): int
    {
        $notified = 0;

        $pageUrls = SeoAdvancedAudit::where('site_id', $siteId)->distinct()->pluck('page_url');

        foreach ($pageUrls as $pageUrl) {
            $recent = SeoAdvancedAudit::where('site_id', $siteId)->where('page_url', $pageUrl)
                ->orderByDesc('audited_at')->limit(2)->get();

            if ($recent->count() < 2) {
                continue;
            }

            [$latest, $previous] = [$recent[0], $recent[1]];
            if ($previous->score - $latest->score >= self::SCORE_DROP_THRESHOLD) {
                $dispatcher->notifySiteAdmin(
                    $siteId,
                    'کاهش امتیاز SEO',
                    "امتیاز SEO صفحه «{$pageUrl}» از {$previous->score} به {$latest->score} کاهش یافت."
                );
                $notified++;
            }
        }

        return $notified;
    }
}
