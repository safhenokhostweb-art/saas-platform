<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Services\TechnicalSeoScannerService;
use Tests\TestCase;

class TechnicalSeoScannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function scanner(): TechnicalSeoScannerService
    {
        return new TechnicalSeoScannerService();
    }

    public function test_flags_broken_links(): void
    {
        $result = $this->scanner()->scan(1, '/page', null, null, [
            'links' => [['url' => 'https://x.com/ok', 'status' => 200], ['url' => 'https://x.com/gone', 'status' => 404]],
        ]);

        $this->assertCount(1, array_filter($result['issues'], fn ($i) => $i['code'] === 'broken_link'));
    }

    public function test_flags_missing_viewport_meta(): void
    {
        $result = $this->scanner()->scan(1, '/page', null, null, ['has_viewport_meta' => false]);

        $this->assertTrue(collect($result['issues'])->contains('code', 'missing_viewport_meta'));
    }

    public function test_does_not_flag_viewport_when_present(): void
    {
        $result = $this->scanner()->scan(1, '/page', null, null, ['has_viewport_meta' => true]);

        $this->assertFalse(collect($result['issues'])->contains('code', 'missing_viewport_meta'));
    }

    public function test_flags_oversized_page_and_images(): void
    {
        $result = $this->scanner()->scan(1, '/page', null, null, [
            'html_size_bytes' => 3 * 1024 * 1024,
            'images' => [['src' => 'big.jpg', 'size_bytes' => 500 * 1024]],
        ]);

        $this->assertTrue(collect($result['issues'])->contains('code', 'page_size_too_large'));
        $this->assertTrue(collect($result['issues'])->contains('code', 'image_too_large'));
    }

    public function test_detects_duplicate_title_across_pages_of_the_same_site(): void
    {
        SeoAdvancedAudit::create(['site_id' => 10, 'page_url' => '/a', 'title' => 'عنوان یکسان', 'score' => 90]);

        $result = $this->scanner()->scan(10, '/b', 'عنوان یکسان', null);

        $this->assertTrue(collect($result['issues'])->contains('code', 'duplicate_title'));
    }

    public function test_duplicate_title_check_never_flags_the_same_page_against_itself(): void
    {
        SeoAdvancedAudit::create(['site_id' => 10, 'page_url' => '/a', 'title' => 'عنوان', 'score' => 90]);

        $result = $this->scanner()->scan(10, '/a', 'عنوان', null);

        $this->assertFalse(collect($result['issues'])->contains('code', 'duplicate_title'));
    }

    public function test_duplicate_title_check_never_mixes_across_sites(): void
    {
        SeoAdvancedAudit::create(['site_id' => 20, 'page_url' => '/a', 'title' => 'عنوان مشترک', 'score' => 90]);

        $result = $this->scanner()->scan(21, '/b', 'عنوان مشترک', null);

        $this->assertFalse(collect($result['issues'])->contains('code', 'duplicate_title'));
    }

    public function test_detects_duplicate_description(): void
    {
        SeoAdvancedAudit::create(['site_id' => 30, 'page_url' => '/a', 'description' => 'توضیح یکسان', 'score' => 90]);

        $result = $this->scanner()->scan(30, '/b', null, 'توضیح یکسان');

        $this->assertTrue(collect($result['issues'])->contains('code', 'duplicate_description'));
    }

    public function test_score_penalty_reflects_issue_severities(): void
    {
        $result = $this->scanner()->scan(1, '/page', null, null, [
            'links' => [['url' => 'x', 'status' => 500]], // critical = 20
            'has_viewport_meta' => false, // critical = 20
        ]);

        $this->assertSame(40, $result['score_penalty']);
    }
}
