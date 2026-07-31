<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use Tests\TestCase;

/**
 * Phase 18-D — Part 4: Keyword Tracking Foundation — simple storage +
 * manual position history only, no external rank-tracking service.
 */
class SeoAdvancedKeywordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_keyword_defaults_to_active_status_with_no_position(): void
    {
        $keyword = SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'خرید آنلاین']);

        $this->assertSame('active', $keyword->status);
        $this->assertNull($keyword->position);
        $this->assertNull($keyword->position_history);
    }

    public function test_recording_a_position_updates_current_position_and_appends_history(): void
    {
        $keyword = SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'خرید آنلاین']);

        $keyword->recordPosition(12, '2026-07-01');
        $keyword->recordPosition(8, '2026-07-15');

        $keyword->refresh();

        $this->assertSame(8, $keyword->position);
        $this->assertCount(2, $keyword->position_history);
        $this->assertSame(['date' => '2026-07-01', 'position' => 12], $keyword->position_history[0]);
        $this->assertSame(['date' => '2026-07-15', 'position' => 8], $keyword->position_history[1]);
    }

    public function test_target_url_and_status_are_storable(): void
    {
        $keyword = SeoAdvancedKeyword::create([
            'site_id' => 1, 'keyword' => 'کفش ورزشی', 'target_url' => '/shoes', 'status' => SeoAdvancedKeyword::STATUS_PAUSED,
        ]);

        $this->assertSame('/shoes', $keyword->target_url);
        $this->assertSame('paused', $keyword->status);
    }
}
