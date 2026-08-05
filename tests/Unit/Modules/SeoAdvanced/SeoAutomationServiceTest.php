<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeywordPosition;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAutomationService;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAuditor;
use Tests\TestCase;

/**
 * Phase 18-E — Part 8/11: dispatches through SiteNotificationDispatcherInterface
 * — the SAME extension point CRM's automation already uses (Phase 20) —
 * never a parallel system. Uses a recording fake, exactly like
 * CrmTaskReminderServiceTest (Phase 20).
 */
class SeoAutomationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDispatcher(): object
    {
        return new class implements SiteNotificationDispatcherInterface {
            public array $calls = [];

            public function notifySiteAdmin(int $siteId, string $title, string $message): void
            {
                $this->calls[] = compact('siteId', 'title', 'message');
            }
        };
    }

    public function test_detects_a_significant_rank_drop(): void
    {
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        // Fully configured so the technical-health check (a separate concern,
        // covered by its own tests below) never fires here — isolates this
        // test to rank-drop detection only.
        SeoAdvancedSetting::create(['site_id' => 1, 'robots_directive' => 'index,follow', 'schema_type' => 'website']);

        $keyword = SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'خرید کفش']);
        SeoAdvancedKeywordPosition::create(['site_id' => 1, 'keyword_id' => $keyword->id, 'position' => 3, 'recorded_at' => '2026-01-01']);
        SeoAdvancedKeywordPosition::create(['site_id' => 1, 'keyword_id' => $keyword->id, 'position' => 12, 'recorded_at' => '2026-01-10']);

        $service = new SeoAutomationService(new SeoAuditor());
        $counts = $service->runForSite(1);

        $this->assertSame(1, $counts['rank_drops']);
        $this->assertCount(1, $fake->calls);
    }

    public function test_small_rank_change_is_not_flagged(): void
    {
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        $keyword = SeoAdvancedKeyword::create(['site_id' => 2, 'keyword' => 'x']);
        SeoAdvancedKeywordPosition::create(['site_id' => 2, 'keyword_id' => $keyword->id, 'position' => 5, 'recorded_at' => '2026-01-01']);
        SeoAdvancedKeywordPosition::create(['site_id' => 2, 'keyword_id' => $keyword->id, 'position' => 7, 'recorded_at' => '2026-01-10']);

        $counts = (new SeoAutomationService(new SeoAuditor()))->runForSite(2);

        $this->assertSame(0, $counts['rank_drops']);
    }

    public function test_detects_missing_sitemap_and_technical_configuration(): void
    {
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        // No SeoAdvancedSetting row at all for site 3 -> robots/schema both "not configured".
        $counts = (new SeoAutomationService(new SeoAuditor()))->runForSite(3);

        $this->assertSame(2, $counts['technical_errors']); // robots + schema
        $this->assertSame(0, $counts['sitemap_errors']); // sitemap_available is always true once installed
    }

    public function test_no_technical_errors_when_fully_configured(): void
    {
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        SeoAdvancedSetting::create(['site_id' => 4, 'robots_directive' => 'index,follow', 'schema_type' => 'website']);

        $counts = (new SeoAutomationService(new SeoAuditor()))->runForSite(4);

        $this->assertSame(0, $counts['technical_errors']);
    }

    public function test_detects_a_significant_score_drop_for_the_same_page(): void
    {
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        SeoAdvancedAudit::create(['site_id' => 5, 'page_url' => '/x', 'score' => 90, 'audited_at' => '2026-01-01']);
        SeoAdvancedAudit::create(['site_id' => 5, 'page_url' => '/x', 'score' => 70, 'audited_at' => '2026-01-10']);

        $counts = (new SeoAutomationService(new SeoAuditor()))->runForSite(5);

        $this->assertSame(1, $counts['score_drops']);
    }

    public function test_automation_never_mixes_data_across_sites(): void
    {
        $fake = $this->fakeDispatcher();
        $this->app->instance(SiteNotificationDispatcherInterface::class, $fake);

        $keywordSiteA = SeoAdvancedKeyword::create(['site_id' => 100, 'keyword' => 'a']);
        SeoAdvancedKeywordPosition::create(['site_id' => 100, 'keyword_id' => $keywordSiteA->id, 'position' => 2, 'recorded_at' => '2026-01-01']);
        SeoAdvancedKeywordPosition::create(['site_id' => 100, 'keyword_id' => $keywordSiteA->id, 'position' => 20, 'recorded_at' => '2026-01-10']);

        $keywordSiteB = SeoAdvancedKeyword::create(['site_id' => 101, 'keyword' => 'b']);
        SeoAdvancedKeywordPosition::create(['site_id' => 101, 'keyword_id' => $keywordSiteB->id, 'position' => 4, 'recorded_at' => '2026-01-01']);
        SeoAdvancedKeywordPosition::create(['site_id' => 101, 'keyword_id' => $keywordSiteB->id, 'position' => 6, 'recorded_at' => '2026-01-10']); // small change, not a drop

        $countsSiteB = (new SeoAutomationService(new SeoAuditor()))->runForSite(101);

        // Site A's real rank-drop row exists in the DB, but runForSite(101)
        // must never touch it — proves the query is scoped by site_id, not
        // merely "no data happened to exist" (site 101 has its own,
        // deliberately non-triggering keyword). Technical-check notifications
        // may still legitimately fire for site 101 (it has no SeoAdvancedSetting
        // row at all), so only rank-drop notifications are asserted here.
        $this->assertSame(0, $countsSiteB['rank_drops']);
        $this->assertFalse(collect($fake->calls)->contains('title', 'افت رتبه کلمه کلیدی'));
        foreach ($fake->calls as $call) {
            $this->assertSame(101, $call['siteId']);
        }
    }
}
