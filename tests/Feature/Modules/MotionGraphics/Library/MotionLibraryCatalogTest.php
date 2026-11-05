<?php

namespace Tests\Feature\Modules\MotionGraphics\Library;

use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup;
use Tests\TestCase;

/**
 * ADR-078: the curated motion library is published into the catalog with
 * real, resolvable compiled assets, respecting version immutability.
 */
final class MotionLibraryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function sync(): array
    {
        return $this->app->make(MotionLibraryCatalogService::class)->sync();
    }

    public function test_every_published_reference_is_served_with_the_font_embedded_and_hardened_headers(): void
    {
        foreach (MotionLibraryCatalogService::ASSETS as $slug => $libraryVersion) {
            $response = $this->get('/'.MotionLibraryCatalogService::assetPath($slug, $libraryVersion));

            $response->assertOk();
            $this->assertStringStartsWith('image/svg+xml', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
            $this->assertStringContainsString('font-src data:', $response->headers->get('Content-Security-Policy'));
            $this->assertSame('cross-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
            $this->assertNull($response->headers->get('Set-Cookie'), 'a static asset must not start a session');

            $body = $response->getContent();
            $this->assertSame(MotionLibraryCatalogService::render($slug, $libraryVersion, []), $body);
            // ADR-079: Vazirmatn travels inside the SVG (an <img> cannot load fonts).
            $this->assertStringContainsString("@font-face{font-family:'Vazirmatn';src:url(data:font/woff2;base64,", $body);
            $this->assertNotFalse(simplexml_load_string($body));
        }
    }

    public function test_query_parameters_customise_the_served_svg_safely(): void
    {
        $response = $this->get('/motion/business-growth-motion/v2/animation.svg?'.http_build_query([
            'report_title' => 'گزارش <b>ویژه</b> 1405',
            'share_percent' => '72',
            'primary' => '#7C3AED',
            'speed' => 'fast',
            'loop' => '0',
            'accent' => 'javascript:alert(1)',
            'nope' => 'x',
        ]));

        $response->assertOk();
        $svg = $response->getContent();
        $this->assertStringContainsString('data-p="report_title">گزارش &lt;b&gt;ویژه&lt;/b&gt; ۱۴۰۵</text>', $svg);
        $this->assertStringContainsString('data-p="share_percent">۷۲٪</text>', $svg);
        $this->assertStringContainsString('--asset-primary:#7c3aed', $svg);
        $this->assertStringContainsString('--p-share_percent:72;--p-share_percent-rest:28', $svg);
        $this->assertStringContainsString('--k:0.7', $svg);
        $this->assertStringContainsString('*{animation-iteration-count:1!important}', $svg);
        $this->assertStringNotContainsString('javascript', $svg);
        $this->assertStringNotContainsString('<b>', $svg);

        // An oversized query is refused outright.
        $this->get('/motion/hero-motion/v2/animation.svg?window_title='.str_repeat('a', 5000))->assertStatus(414);
    }

    public function test_v1_is_still_served_byte_for_byte_as_published(): void
    {
        $file = MotionLibraryCatalogService::libraryDirectory('hero-motion', 'v1').'/animation.svg';

        $this->assertSame(file_get_contents($file), $this->get('/motion/hero-motion/v1/animation.svg?window_title=x')->getContent());
    }

    public function test_only_catalog_slugs_and_existing_versions_are_served(): void
    {
        foreach ([
            '/motion/not-a-motion/v1/animation.svg',
            '/motion/hero-motion/v99/animation.svg',
            '/motion/hero-motion/v3/animation.svg',
            '/motion/hero-motion/v0/animation.svg',
            '/motion/hero-motion/..%2F..%2Fmetadata.json/animation.svg',
            '/motion/ai-business-assistant/v1/animation.svg',
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_sync_publishes_both_assets_with_valid_compiled_assets_and_tiers(): void
    {
        $result = $this->sync();

        $expectedTier = ['hero-motion' => 'free', 'business-growth-motion' => 'premium'];
        foreach (MotionLibraryCatalogService::ASSETS as $slug => $libraryVersion) {
            $motion = MotionGraphic::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame('published', $motion->status);
            $this->assertSame(1, $motion->published_version);
            $this->assertSame($expectedTier[$slug], $motion->licenseTier());
            $this->assertSame($motion->uuid, $result[$slug]['uuid']);

            $version = $motion->versions()->where('version', 1)->firstOrFail();
            $this->assertSame('published', $version->status);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $version->source_checksum);

            // ADR-079: size/sha256 describe the bytes actually served for
            // the default parameters (font embedded), not the source file.
            $served = MotionLibraryCatalogService::render($slug, $libraryVersion, []);
            $asset = $version->compiled_assets[0];
            $this->assertSame('svg', $asset['type']);
            $this->assertSame('/'.MotionLibraryCatalogService::assetPath($slug, $libraryVersion), $asset['url']);
            $this->assertSame('image/svg+xml', $asset['mime_type']);
            $this->assertSame(strlen($served), $asset['size']);
            $this->assertSame(hash('sha256', $served), $asset['sha256']);

            // The public runtime-parameter contract is stored for the manifest.
            $declared = json_decode(file_get_contents(MotionLibraryCatalogService::libraryDirectory($slug, $libraryVersion).'/metadata.json'), true)['parameters'];
            $this->assertCount(count($declared), $motion->runtime_configuration['parameters']);
        }
    }

    public function test_a_version_a_site_is_bound_to_stays_published_until_the_site_upgrades(): void
    {
        $this->test_a_changed_library_file_becomes_a_new_version_and_the_old_one_is_retired_untouched();
        // (above: v1 had no site bound, so it was retired)

        $motion = MotionGraphic::query()->where('slug', 'business-growth-motion')->firstOrFail();
        $old = MotionGraphicVersion::query()->create([
            'motion_graphic_id' => $motion->id, 'version' => 9, 'source_snapshot' => [], 'editable_schema_snapshot' => [],
            'compiled_assets' => [['url' => '/motion/business-growth-motion/v1/animation.svg', 'sha256' => str_repeat('1', 64)]],
            'source_checksum' => str_repeat('1', 64), 'status' => 'published', 'change_description' => 'bound', 'created_by_actor_identifier' => 'test',
        ]);
        $site = \App\Models\Site::query()->create([
            'customer_id' => \App\Models\Customer::query()->create(['name' => 'C', 'mobile' => '09120000777'])->id,
            'title' => 'Bound site',
        ]);
        $template = \App\Modules\MotionGraphics\Models\TemplateAssetPackage::query()->create(['template_reference' => 't', 'required_assets' => [], 'package_version' => 1]);
        $package = \App\Modules\MotionGraphics\Models\SiteAssetPackage::query()->create(['site_id' => $site->id, 'template_reference' => 't', 'template_asset_package_id' => $template->id]);
        \App\Modules\MotionGraphics\Models\SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $package->id, 'asset_id' => $motion->uuid, 'active_version' => 9,
            'bound_checksum' => 'x', 'installed_at' => now(),
        ]);

        $this->sync();
        $this->assertSame('published', $old->fresh()->status, 'a bound version must stay readable (VERSION_BINDING_RULES)');

        \App\Modules\MotionGraphics\Models\SiteAssetPackageItem::query()->update(['active_version' => 1]);
        $this->sync();
        $this->assertSame('retired', $old->fresh()->status, 'once unbound, the next sync retires it');
    }

    public function test_the_read_contracts_project_the_published_file_and_expose_the_tier(): void
    {
        $uuid = $this->sync()['business-growth-motion']['uuid'];

        $projection = $this->app->make(MotionRuntimeProjectionReadContract::class)->projectAsset($uuid);
        $this->assertNotNull($projection);
        $this->assertSame('/motion/business-growth-motion/v2/animation.svg', $projection->optimizedReference);
        $this->assertSame(['width' => 960, 'height' => 540, 'viewBox' => '0 0 960 540'], $projection->placementRules['dimensions']);
        $keys = array_column($projection->parameters, 'key');
        $this->assertContains('share_percent', $keys);
        $this->assertContains('speed', $keys);

        $asset = $this->app->make(MotionAssetReadContract::class)->findByCanonicalId($uuid);
        $this->assertSame('premium', $asset->compatibility['license_tier']);
        $this->assertSame('موشن رشد کسب‌وکار', $asset->name);
    }

    public function test_sync_is_idempotent(): void
    {
        $first = $this->sync();
        $second = $this->sync();

        $this->assertSame($first['hero-motion']['uuid'], $second['hero-motion']['uuid']);
        $this->assertFalse($second['hero-motion']['created']);
        $this->assertSame(2, MotionGraphicVersion::query()->count());
    }

    public function test_a_changed_library_file_becomes_a_new_version_and_the_old_one_is_retired_untouched(): void
    {
        $group = MotionGroup::query()->create(['name' => 'هیرو', 'slug' => 'hero', 'ownership_type' => 'global', 'search_metadata' => [], 'sort_order' => 1, 'is_active' => true]);
        $motion = MotionGraphic::query()->create([
            'name' => 'old', 'slug' => 'hero-motion', 'ownership_type' => MotionGraphic::OWNERSHIP_GLOBAL, 'group_id' => $group->id,
            'engine' => 'svg-js', 'asset_type' => 'svg', 'source_definition_json' => ['old' => true], 'editable_schema_json' => [],
            'published_version' => 1, 'status' => 'published', 'created_by_actor_identifier' => 'test',
        ]);
        $oldAssets = [['type' => 'svg', 'url' => '/motion/hero-motion/v0/animation.svg', 'sha256' => str_repeat('0', 64), 'size' => 1]];
        MotionGraphicVersion::query()->create([
            'motion_graphic_id' => $motion->id, 'version' => 1, 'source_snapshot' => [], 'editable_schema_snapshot' => [],
            'compiled_assets' => $oldAssets, 'source_checksum' => str_repeat('0', 64), 'status' => 'published',
            'change_description' => 'old', 'created_by_actor_identifier' => 'test',
        ]);

        $result = $this->sync();

        $this->assertSame(2, $result['hero-motion']['version']);
        $this->assertSame(2, $motion->fresh()->published_version);
        $v1 = $motion->versions()->where('version', 1)->first();
        $this->assertSame('retired', $v1->status);
        // assertEquals: MySQL's JSON column does not preserve key order.
        $this->assertEquals($oldAssets, $v1->compiled_assets, 'a historical version is never rewritten');
        $this->assertSame(1, $motion->versions()->where('version', 2)->first()->parent_version);
    }

    public function test_historical_versions_stay_immutable(): void
    {
        $this->sync();
        $version = MotionGraphicVersion::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $version->update(['compiled_assets' => [['url' => '/elsewhere.svg']]]);
    }

    public function test_the_legacy_hero_vector_showcase_record_is_archived_with_its_history_kept(): void
    {
        $group = MotionGroup::query()->create(['name' => 'هیرو', 'slug' => 'hero', 'ownership_type' => 'global', 'search_metadata' => [], 'sort_order' => 1, 'is_active' => true]);
        $legacy = MotionGraphic::query()->create([
            'name' => 'legacy', 'slug' => 'hero-vector-showcase', 'ownership_type' => MotionGraphic::OWNERSHIP_GLOBAL, 'group_id' => $group->id,
            'engine' => 'svg-css', 'asset_type' => 'svg', 'source_definition_json' => [], 'editable_schema_json' => [],
            'published_version' => 1, 'status' => 'published', 'created_by_actor_identifier' => 'system',
        ]);
        MotionGraphicVersion::query()->create([
            'motion_graphic_id' => $legacy->id, 'version' => 1, 'source_snapshot' => [], 'editable_schema_snapshot' => [],
            'compiled_assets' => [], 'source_checksum' => str_repeat('0', 64), 'status' => 'published',
            'change_description' => 'legacy', 'created_by_actor_identifier' => 'system',
        ]);

        $this->sync();

        $legacy->refresh();
        $this->assertSame('archived', $legacy->status);
        $this->assertNull($legacy->published_version);
        $this->assertNotNull($legacy->archived_at);
        $this->assertSame('retired', $legacy->versions()->first()->status);
        $this->assertNull($this->app->make(MotionRuntimeProjectionReadContract::class)->projectAsset($legacy->uuid));
    }

    public function test_a_missing_tier_means_premium(): void
    {
        $this->assertSame('premium', (new MotionGraphic(['runtime_configuration' => []]))->licenseTier());
        $this->assertSame('premium', (new MotionGraphic(['runtime_configuration' => ['license_tier' => 'FREE']]))->licenseTier());
        $this->assertSame('free', (new MotionGraphic(['runtime_configuration' => ['license_tier' => 'free']]))->licenseTier());
    }
}
