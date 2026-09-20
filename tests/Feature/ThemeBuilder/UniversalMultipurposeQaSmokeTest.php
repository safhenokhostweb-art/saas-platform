<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\Infrastructure\MediaStorage;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Part 7 (7-part local audit task, 2026-09-19) — end-to-end smoke check
 * tying together the fixes from Parts 3/4/5/6: the theme is genuinely
 * visible through the real commercial-eligibility path (not a status flag
 * set by hand), and its actual rendered home-page HTML contains no
 * broken/empty image references.
 */
class UniversalMultipurposeQaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_is_visible_in_marketplace_after_the_real_publish_path(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        self::assertFalse($theme->isCommerciallyEligible(), 'a freshly-seeded theme must not be eligible before a real publish — draft status, no release yet');

        app(ThemeBuilderService::class)->publish($theme, '1.0.0', 'Smoke test publish.');
        $theme->refresh();

        self::assertTrue($theme->isPublished());
        self::assertTrue($theme->isCommerciallyEligible(), 'the real Theme Builder publish path (quality gate + release) must make the theme commercially eligible');
        self::assertTrue(
            Theme::query()->marketplace()->where('id', $theme->id)->exists(),
            'the theme must appear in the real Marketplace scope (is_marketplace=true AND status=published), not just have a flag set by hand'
        );
    }

    public function test_home_page_renders_with_no_broken_or_empty_images(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        $home = $theme->getPageList()['home'] ?? null;
        self::assertNotNull($home, 'the theme must have a real home page');

        $html = app(BlockRenderer::class)->renderBlocks($home['blocks'] ?? []);

        self::assertNotEmpty($html);

        // Every <img src="..."> and inline background-image url(...) on the
        // real home page must be a non-empty, locally-resolvable URL — the
        // Part 3 root cause (UploadedFile::fake()->image() producing
        // structurally-valid-but-visually-blank JPEGs) is closed at the
        // asset-generation level (see UniversalMultipurposeQaThemeSeederTest::
        // test_seeded_images_are_real_non_blank_jpegs); this proves the
        // RENDERED page actually references those real files, with no
        // empty src="" left over from a null/missing image prop anywhere
        // in the real authored content.
        preg_match_all('/src="([^"]*)"/', $html, $srcMatches);
        preg_match_all('/background-image:\s*url\(([^)]*)\)/', $html, $bgMatches);
        $allImageRefs = array_merge($srcMatches[1] ?? [], array_map(fn ($u) => trim($u, "'\" "), $bgMatches[1] ?? []));

        self::assertNotEmpty($allImageRefs, 'the home page must reference at least one image (hero/about/gallery/team)');

        $disk = app(MediaStorage::class)->diskNameFor('public');
        foreach ($allImageRefs as $ref) {
            self::assertNotSame('', $ref, 'no image reference on the home page may be empty');
            $relative = ltrim(parse_url($ref, PHP_URL_PATH) ?? '', '/');
            $relative = preg_replace('#^storage/#', '', $relative);
            self::assertTrue(
                Storage::disk($disk)->exists($relative),
                "referenced image does not exist on disk: {$ref}"
            );
        }
    }
}
