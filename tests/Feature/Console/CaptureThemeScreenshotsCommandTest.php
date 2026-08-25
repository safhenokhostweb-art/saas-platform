<?php

namespace Tests\Feature\Console;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Theme Builder commercial polish audit, Phase 3: theme:capture-screenshots
 * used to REPLACE the full screenshots list with only the pages captured in
 * that run, and could overwrite the thumbnail with a non-home page. Running
 * `--pages=contact` after a full capture destroyed the home/about entries —
 * this reproduces that exact regression and asserts the merge-not-overwrite
 * fix.
 */
class CaptureThemeScreenshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeTheme(string $slug = 'restaurant-premium'): Theme
    {
        $pages = [
            'home'    => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []],
            'about'   => ['title' => 'درباره ما', 'slug' => 'about', 'blocks' => []],
            'contact' => ['title' => 'تماس با ما', 'slug' => 'contact', 'blocks' => []],
        ];

        $theme = Theme::create([
            'name' => 'رستوران پریمیوم',
            'slug' => $slug,
            'description' => 'test theme',
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'is_marketplace' => true,
            'marketplace_price' => 290000,
            'category' => Theme::CATEGORY_RESTAURANT,
            'version' => 1,
            'pages' => $pages,
        ]);

        ThemeMarketplace::create(['theme_id' => $theme->id, 'marketplace_description' => 'test']);

        return $theme;
    }

    private function fakePlaywright(bool $succeeds = true): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) use ($succeeds) {
            $mock->shouldReceive('availabilityReport')->andReturn(['ready' => true]);
            if ($succeeds) {
                $mock->shouldReceive('extract')->andReturn([
                    'success' => true,
                    'screenshot_base64' => base64_encode('fake-png-bytes'),
                ]);
            } else {
                $mock->shouldReceive('extract')->andReturn(['success' => false]);
            }
        });
    }

    public function test_full_capture_creates_the_complete_screenshot_set(): void
    {
        Storage::fake('public');
        $this->fakePlaywright();
        $theme = $this->makeTheme();

        $this->artisan('theme:capture-screenshots', ['--theme' => $theme->slug])->assertExitCode(0);

        $theme->refresh();
        $meta = $theme->marketplaceMeta;

        $this->assertCount(3, $meta->screenshots);
        $this->assertStringContainsString('restaurant-premium-home.png', $theme->thumbnail);
        $this->assertStringContainsString('restaurant-premium-home.png', $theme->preview_image);
    }

    public function test_partial_capture_updates_only_the_requested_page_and_preserves_the_rest(): void
    {
        Storage::fake('public');
        $this->fakePlaywright();
        $theme = $this->makeTheme();

        // Simulate a prior full capture already having happened.
        $existing = [
            'http://saas-platform.test/storage/theme-screenshots/restaurant-premium-home.png',
            'http://saas-platform.test/storage/theme-screenshots/restaurant-premium-about.png',
            'http://saas-platform.test/storage/theme-screenshots/restaurant-premium-contact.png',
        ];
        $theme->update(['thumbnail' => $existing[0], 'preview_image' => $existing[0]]);
        $theme->marketplaceMeta->update(['screenshots' => $existing]);

        $this->artisan('theme:capture-screenshots', [
            '--theme' => $theme->slug,
            '--pages' => 'contact',
        ])->assertExitCode(0);

        $theme->refresh();
        $meta = $theme->marketplaceMeta->fresh();

        // Exactly 3 entries — no duplicates, nothing lost.
        $this->assertCount(3, $meta->screenshots);
        $this->assertContains($existing[0], $meta->screenshots, 'home screenshot must be preserved');
        $this->assertContains($existing[1], $meta->screenshots, 'about screenshot must be preserved');
        $this->assertNotContains($existing[2], $meta->screenshots, 'contact screenshot must have been replaced with a fresh URL');

        // A partial run that never touched "home" must NOT clobber the thumbnail.
        $this->assertSame($existing[0], $theme->thumbnail);
        $this->assertSame($existing[0], $theme->preview_image);
    }

    public function test_failed_capture_does_not_destroy_existing_screenshots(): void
    {
        Storage::fake('public');
        $theme = $this->makeTheme();

        $existing = [
            'http://saas-platform.test/storage/theme-screenshots/restaurant-premium-home.png',
            'http://saas-platform.test/storage/theme-screenshots/restaurant-premium-about.png',
            'http://saas-platform.test/storage/theme-screenshots/restaurant-premium-contact.png',
        ];
        $theme->update(['thumbnail' => $existing[0], 'preview_image' => $existing[0]]);
        $theme->marketplaceMeta->update(['screenshots' => $existing]);

        $this->fakePlaywright(succeeds: false);

        $this->artisan('theme:capture-screenshots', [
            '--theme' => $theme->slug,
            '--pages' => 'contact',
        ])->assertExitCode(0);

        $theme->refresh();
        $meta = $theme->marketplaceMeta->fresh();

        $this->assertSame($existing, $meta->screenshots);
        $this->assertSame($existing[0], $theme->thumbnail);
    }
}
