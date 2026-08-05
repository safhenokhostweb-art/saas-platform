<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\RankProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeywordPosition;
use SaaSPlatform\Modules\SeoAdvanced\Services\ManualRankProvider;
use SaaSPlatform\Modules\SeoAdvanced\Services\RankTrackingService;
use Tests\TestCase;

class RankTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_recording_writes_both_legacy_json_and_normalized_table(): void
    {
        $keyword = SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'خرید کفش']);
        $service = new RankTrackingService(new ManualRankProvider());

        $service->recordManual($keyword, 7, 'https://example.com/shoes', 'google', '2026-01-01');

        $keyword->refresh();
        $this->assertSame(7, $keyword->position);
        $this->assertCount(1, $keyword->position_history); // Phase 18-D backward compat, unchanged

        $this->assertDatabaseHas('seo_advanced_keyword_positions', [
            'keyword_id' => $keyword->id, 'position' => 7, 'url' => 'https://example.com/shoes',
            'search_engine' => 'google', 'source' => 'manual',
        ]);
    }

    public function test_default_manual_provider_makes_sweep_a_no_op(): void
    {
        SeoAdvancedKeyword::create(['site_id' => 5, 'keyword' => 'a']);
        SeoAdvancedKeyword::create(['site_id' => 5, 'keyword' => 'b']);

        $service = new RankTrackingService(new ManualRankProvider());
        $updated = $service->sweep(5);

        $this->assertSame(0, $updated);
        $this->assertSame(0, SeoAdvancedKeywordPosition::count());
    }

    public function test_sweep_uses_whatever_provider_is_bound(): void
    {
        SeoAdvancedKeyword::create(['site_id' => 6, 'keyword' => 'a', 'status' => SeoAdvancedKeyword::STATUS_ACTIVE]);
        $paused = SeoAdvancedKeyword::create(['site_id' => 6, 'keyword' => 'b', 'status' => SeoAdvancedKeyword::STATUS_PAUSED]);

        $fakeProvider = new class implements RankProviderInterface {
            public function name(): string { return 'fake'; }
            public function fetchPosition(int $siteId, string $keyword, ?string $url, string $searchEngine = 'google'): ?int { return 3; }
        };

        $service = new RankTrackingService($fakeProvider);
        $updated = $service->sweep(6);

        $this->assertSame(1, $updated); // paused keyword is skipped
        $this->assertDatabaseHas('seo_advanced_keyword_positions', ['keyword_id' => SeoAdvancedKeyword::where('keyword', 'a')->value('id'), 'source' => 'fake']);
        $this->assertSame(0, SeoAdvancedKeywordPosition::where('keyword_id', $paused->id)->count());
    }

    public function test_history_returns_entries_oldest_first(): void
    {
        $keyword = SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'x']);
        $service = new RankTrackingService(new ManualRankProvider());

        $service->recordManual($keyword, 10, null, 'google', '2026-01-01');
        $service->recordManual($keyword, 8, null, 'google', '2026-01-15');

        $history = $service->history($keyword);

        $this->assertCount(2, $history);
        $this->assertSame(10, $history->first()->position);
        $this->assertSame(8, $history->last()->position);
    }

    public function test_history_can_be_filtered_by_search_engine(): void
    {
        $keyword = SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'x']);
        $service = new RankTrackingService(new ManualRankProvider());

        $service->recordManual($keyword, 5, null, 'google');
        $service->recordManual($keyword, 12, null, 'bing');

        $this->assertCount(1, $service->history($keyword, 'bing'));
        $this->assertCount(1, $service->history($keyword, 'google'));
        $this->assertCount(2, $service->history($keyword));
    }
}
