<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Services\MotionGraphicsAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * FINAL Motion UX Closure — the real visual Admin picker
 * (MotionGraphicsAdminService::browseLibrary(), backed by the new,
 * additive MotionAssetLibraryBrowseContract). Proves: only published,
 * global assets are browsable; search/category cannot surface anything
 * that shouldn't be visible; the payload never carries sensitive fields;
 * runtime projection (thumbnail) still flows through the existing
 * MotionRuntimeProjectionReadContract; the flag/auth gate is unchanged.
 *
 * DatabaseMotionAssetLibraryBrowseReader's own SQL (ownership_type=global,
 * whereNotNull(published_version)) is verified by code review, not a live
 * DB test — the standalone module's own `motion_graphics` table is not
 * migrated on this host, the same pre-existing limitation every other
 * Motion Graphics read-contract test in this suite already works around
 * via a bound fake (see bindPublishedAsset()/bindLibraryBrowse()).
 */
class MotionAssetBrowseTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->fakeActivityLogger();
    }

    private function asset(string $id, string $name, ?string $category = 'medical'): AssetReadModel
    {
        return new AssetReadModel($id, 1, 'svg', $category, [], 'checksum', false, $name);
    }

    // ── #1 published reusable (global) assets appear ────────────────────

    public function test_published_global_assets_appear_in_the_browse_results(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindLibraryBrowse([
            $this->asset('medical-hero-intro', 'Medical Hero Intro'),
            $this->asset('medical-process-flow', 'Medical Process Flow'),
        ], 2);
        $this->bindPublishedAsset('medical-hero-intro', 1, 'Medical Hero Intro', 'medical');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $result = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary([], 1, 24, $context);

        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['total']);
        $this->assertSame('medical-hero-intro', $result['items'][0]->assetId);
        $this->assertSame('Medical Hero Intro', $result['items'][0]->name);
    }

    // ── #7 runtime still uses the existing projection contract (thumbnail) ──

    public function test_browse_results_carry_a_thumbnail_from_the_existing_projection_contract(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindLibraryBrowse([$this->asset('medical-hero-intro', 'Medical Hero Intro')], 1);
        $this->bindPublishedAsset('medical-hero-intro', 1, 'Medical Hero Intro', 'medical');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $result = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary([], 1, 24, $context);

        $this->assertSame('/motion/x/v1/a.json', $result['items'][0]->thumbnailReference);
    }

    // ── #4 sensitive fields never appear in the picker payload ──────────

    public function test_the_browse_payload_never_contains_sensitive_fields(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindLibraryBrowse([$this->asset('medical-hero-intro', 'Medical Hero Intro')], 1);
        $this->bindPublishedAsset('medical-hero-intro', 1, 'Medical Hero Intro', 'medical');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $result = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary([], 1, 24, $context);

        $payload = json_encode(array_map(fn ($p) => $p->toArray(), $result['items']));
        foreach (['source_definition', 'editable_schema', 'ai_metadata', 'prompt', 'owner_site_identifier', 'credentials'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payload);
        }
        $this->assertSame(
            ['asset_id', 'name', 'category', 'type', 'thumbnail_reference'],
            array_keys($result['items'][0]->toArray())
        );
    }

    // ── #5 search/category filters cannot bypass visibility ─────────────

    public function test_search_only_returns_matches_from_the_already_visible_set(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        // The fake's own visible set already excludes anything
        // unpublished/customer-specific by construction (mirrors the real
        // reader's query) — searching cannot surface anything beyond it.
        $this->bindLibraryBrowse([
            $this->asset('medical-hero-intro', 'Medical Hero Intro'),
            $this->asset('restaurant-cta', 'Restaurant CTA'),
        ], 2);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $result = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary(['search' => 'medical'], 1, 24, $context);

        $this->assertCount(1, $result['items']);
        $this->assertSame('medical-hero-intro', $result['items'][0]->assetId);
    }

    public function test_category_filter_narrows_results(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindLibraryBrowse([
            $this->asset('medical-hero-intro', 'Medical Hero Intro', 'medical'),
            $this->asset('restaurant-cta', 'Restaurant CTA', 'restaurant'),
        ], 2);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $result = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary(['category' => 'restaurant'], 1, 24, $context);

        $this->assertCount(1, $result['items']);
        $this->assertSame('restaurant-cta', $result['items'][0]->assetId);
    }

    // ── empty state ──────────────────────────────────────────────────────

    public function test_empty_library_returns_an_empty_result_not_an_error(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindLibraryBrowse([], 0);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $result = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary([], 1, 24, $context);

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['total']);
    }

    // ── fails closed when disabled ───────────────────────────────────────

    public function test_browse_library_fails_closed_when_flag_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindLibraryBrowse([], 0);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $this->expectException(\App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException::class);
        $this->app->make(MotionGraphicsAdminService::class)->browseLibrary([], 1, 24, $context);
    }

    // ── HTTP endpoint + the themes.blade.php page render correctly ──────

    public function test_the_browse_endpoint_returns_json_with_the_expected_shape(): void
    {
        // The real TenantContextContract (not the bindTrustedSite() fake,
        // which only implements the read-side of the interface) is used
        // here, on purpose — the controller's contextFor() calls ->set(),
        // which only the container's real binding provides. This mirrors
        // AdminWorkflowSuccessTest's own "real routes, real container, real
        // TenantContext" HTTP journey pattern.
        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
        $this->bindLibraryBrowse([$this->asset('medical-hero-intro', 'Medical Hero Intro')], 1);

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.motion-graphics.assets.browse', $site));

        $response->assertOk();
        $response->assertJsonStructure(['items' => [['asset_id', 'name', 'category', 'type', 'thumbnail_reference']], 'total', 'page', 'per_page']);
    }
}
