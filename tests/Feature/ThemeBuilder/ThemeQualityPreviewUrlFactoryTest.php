<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityPreviewUrlFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2g — turns a Theme into a real, deterministic, short-lived preview
 * target automatically, with no caller-supplied URL. Reuses the exact
 * signed route CaptureThemeScreenshotsCommand already uses in production.
 */
class ThemeQualityPreviewUrlFactoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست پیش‌نمایش',
            'slug' => 'preview-factory-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'تست.',
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
        ], $overrides));
    }

    public function test_generates_a_real_signed_url_for_the_home_page(): void
    {
        $theme = $this->makeTheme();

        $url = (new ThemeQualityPreviewUrlFactory())->make($theme);

        $this->assertNotNull($url);
        $this->assertStringContainsString("/{$theme->id}/canvas/home/preview-signed", $url);
        $this->assertStringContainsString('screenshot=1', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_falls_back_to_the_first_page_when_home_is_absent(): void
    {
        $theme = $this->makeTheme(['pages' => ['landing' => ['title' => 'لندینگ', 'slug' => '', 'blocks' => []]]]);

        $url = (new ThemeQualityPreviewUrlFactory())->make($theme);

        $this->assertStringContainsString("/{$theme->id}/canvas/landing/preview-signed", $url);
    }

    public function test_the_generated_url_is_a_genuinely_valid_signed_request(): void
    {
        $theme = $this->makeTheme();
        $url = (new ThemeQualityPreviewUrlFactory())->make($theme);

        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_tampering_with_the_theme_id_invalidates_the_signature(): void
    {
        $theme = $this->makeTheme();
        $other = $this->makeTheme();
        $url = (new ThemeQualityPreviewUrlFactory())->make($theme);

        // Swap the signed theme id path segment for a different one without
        // regenerating the signature — the endpoint must refuse to serve
        // arbitrary data.
        $tampered = str_replace("/{$theme->id}/canvas/home/preview-signed", "/{$other->id}/canvas/home/preview-signed", $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }
}
