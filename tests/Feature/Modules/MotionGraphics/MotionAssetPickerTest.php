<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Services\MotionGraphicsAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Theme Builder Motion Picker — Phase 2 (saas-platform authoring side).
 *
 * MotionGraphicsAdminService::previewAsset() is a thin composition of the
 * exact same MotionAssetReadContract/MotionRuntimeProjectionReadContract
 * every other Motion Graphics read path already uses — no new query, no
 * new asset library. These tests prove: (1) a published asset resolves to
 * a safe display card, (2) an unpublished/unknown asset resolves to
 * nothing, (3) the payload never carries anything beyond
 * asset_id/name/category/type/thumbnail_reference.
 */
class MotionAssetPickerTest extends TestCase
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

    // ── #1 Platform Admin can visually select a published Motion asset ────

    public function test_a_published_asset_resolves_to_a_safe_preview_card(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('medical-hero-intro', 1, 'Medical Hero Intro', 'medical');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $preview = $this->app->make(MotionGraphicsAdminService::class)->previewAsset('medical-hero-intro', $context);

        $this->assertNotNull($preview);
        $this->assertSame('medical-hero-intro', $preview->assetId);
        $this->assertSame('Medical Hero Intro', $preview->name);
        $this->assertSame('medical', $preview->category);
        $this->assertSame('lottie', $preview->type);
        $this->assertSame('/motion/x/v1/a.json', $preview->thumbnailReference);
    }

    // ── #4 unpublished/unknown assets resolve to nothing ────────────────

    public function test_an_unknown_asset_resolves_to_null(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindUnpublishedAsset('medical-hero-intro');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $preview = $this->app->make(MotionGraphicsAdminService::class)->previewAsset('medical-hero-intro', $context);

        $this->assertNull($preview);
    }

    // ── #6 payload never carries sensitive metadata ─────────────────────

    public function test_the_preview_payload_never_contains_sensitive_fields(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('medical-hero-intro', 1, 'Medical Hero Intro', 'medical');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $preview = $this->app->make(MotionGraphicsAdminService::class)->previewAsset('medical-hero-intro', $context);

        $payload = json_encode($preview->toArray());
        foreach (['source_definition', 'editable_schema', 'ai_metadata', 'prompt', 'owner_site_identifier', 'credentials'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payload);
        }
        $this->assertSame(
            ['asset_id', 'name', 'category', 'type', 'thumbnail_reference'],
            array_keys($preview->toArray())
        );
    }

    // ── fails closed when the integration is disabled ───────────────────

    public function test_preview_asset_fails_closed_when_flag_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('medical-hero-intro', 1, 'Medical Hero Intro', 'medical');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $this->expectException(\App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException::class);
        $this->app->make(MotionGraphicsAdminService::class)->previewAsset('medical-hero-intro', $context);
    }
}
