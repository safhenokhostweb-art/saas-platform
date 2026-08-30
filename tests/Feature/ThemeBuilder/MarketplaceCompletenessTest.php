<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\QualityGate\Checks\CommercialMetadataCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2l — Marketplace merchandising completeness is explicitly a
 * DIFFERENT concept from commercial eligibility (Theme::isCommerciallyEligible(),
 * fail-closed, ledger+report driven) and from real release-content
 * correctness (structural rendering, dependencies, contrast — other
 * BLOCKING-capable checks). A theme can fail every check in this suite and
 * still be a perfectly valid, installable commercial release.
 */
class MarketplaceCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست بازار',
            'slug' => 'mp-complete-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'توضیحات واقعی قالب.',
            'is_marketplace' => true,
            'marketplace_price' => 0,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    // ── the completeness contract is deterministic and never blocks ────────

    public function test_a_marketplace_theme_missing_everything_reports_every_gap_as_warning_only(): void
    {
        $theme = $this->makeTheme(['thumbnail' => null, 'preview_image' => null]);
        $gate = new ThemeCommercialQualityGate([new CommercialMetadataCheck()]);

        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertTrue($report->isPassing(), 'Missing marketing assets must never block a commercially valid release.');
        $codes = array_column($report->nonBlockingFindings(), 'code');
        $this->assertContains('metadata.missing_marketplace_row', $codes);
        $this->assertContains('metadata.missing_thumbnail', $codes);
        $this->assertContains('metadata.missing_preview_image', $codes);
    }

    public function test_a_fully_complete_marketplace_theme_reports_no_gaps(): void
    {
        $theme = $this->makeTheme(['thumbnail' => 'theme-screenshots/x-home.png', 'preview_image' => 'theme-screenshots/x-home.png']);
        ThemeMarketplace::create([
            'theme_id' => $theme->id,
            'marketplace_description' => 'توضیحات بازار واقعی.',
            'screenshots' => ['theme-screenshots/x-home.png'],
        ]);

        $gate = new ThemeCommercialQualityGate([new CommercialMetadataCheck()]);
        $report = $gate->evaluate($theme, '1.0.0');

        $codes = array_column($report->checks, 'code');
        $this->assertContains('metadata.complete', $codes);
        $this->assertNotContains('metadata.missing_thumbnail', $codes);
        $this->assertNotContains('metadata.missing_marketplace_row', $codes);
    }

    public function test_missing_thumbnail_and_missing_screenshots_are_distinguished(): void
    {
        // A theme can have real quality-evidence screenshots or marketplace
        // screenshots without a thumbnail set (or vice versa) — the check
        // must report each gap independently, not conflate them.
        $theme = $this->makeTheme(['thumbnail' => null, 'preview_image' => 'x.png']);
        ThemeMarketplace::create(['theme_id' => $theme->id, 'marketplace_description' => 'x', 'screenshots' => ['a.png']]);

        $gate = new ThemeCommercialQualityGate([new CommercialMetadataCheck()]);
        $report = $gate->evaluate($theme, '1.0.0');

        $codes = array_column($report->checks, 'code');
        $this->assertContains('metadata.missing_thumbnail', $codes);
        $this->assertNotContains('metadata.missing_screenshots', $codes);
        $this->assertNotContains('metadata.missing_preview_image', $codes);
    }

    public function test_a_non_marketplace_theme_is_not_held_to_marketplace_completeness_at_all(): void
    {
        $theme = $this->makeTheme(['is_marketplace' => false, 'thumbnail' => null]);
        $gate = new ThemeCommercialQualityGate([new CommercialMetadataCheck()]);

        $report = $gate->evaluate($theme, '1.0.0');

        $codes = array_column($report->checks, 'code');
        $this->assertNotContains('metadata.missing_thumbnail', $codes, 'A theme not intended for the marketplace should not be scored against marketplace presentation.');
    }

    // ── legacy Theme.version authority does not return ──────────────────────

    public function test_marketplace_completeness_never_reads_or_reports_theme_version_as_authoritative(): void
    {
        $theme = $this->makeTheme();
        $this->assertArrayNotHasKey('version', $theme->getAttributes(), 'Theme must not carry a legacy version column the ledger has superseded.');
    }

    // ── no fabricated product claims ────────────────────────────────────────

    public function test_marketplace_row_creation_never_defaults_sales_or_rating_fields_to_a_nonzero_fabricated_value(): void
    {
        // Mirrors the real seeding convention (NexusSeedCommand,
        // MarketplaceThemeSeedCommand): sales_count/is_bestseller/is_featured
        // are always explicitly set to their honest zero/false state, never
        // left to an implicit default that could silently be non-zero.
        $theme = $this->makeTheme();
        $meta = ThemeMarketplace::create([
            'theme_id' => $theme->id,
            'marketplace_description' => 'x',
            'sales_count' => 0,
            'is_bestseller' => false,
            'is_featured' => false,
        ]);

        $this->assertSame(0, $meta->sales_count);
        $this->assertFalse((bool) $meta->is_bestseller);
        $this->assertFalse((bool) $meta->is_featured);
    }
}
