<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use App\Models\Product;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 3G-B-1 (ADR-045) — Commercial Extension Product/Release Foundation.
 *
 * Proves ExtensionReleaseService (the sole writer) gives Extension the same
 * guarantees ThemeReleaseService already gives Theme: unique master
 * identity, immutable append-only releases, safe current-release pointer
 * movement, and fail-closed publication.
 */
class ExtensionReleaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ExtensionReleaseService::class);
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'elements' => [
                ['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'media'],
            ],
        ], $overrides);
    }

    // ── master Extension identity ───────────────────────────────────────────

    public function test_create_extension_master(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $this->assertDatabaseHas('extensions', [
            'vendor_slug' => 'acme',
            'package_slug' => 'gallery-pro',
            'name' => 'Gallery Pro',
        ]);
        $this->assertSame('acme/gallery-pro', $extension->identifier());
        $this->assertNull($extension->current_release_id);
    }

    public function test_vendor_and_package_slug_are_unique_together(): void
    {
        $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $this->expectException(InvalidArgumentException::class);
        $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro Duplicate');
    }

    public function test_same_vendor_may_publish_multiple_independent_products(): void
    {
        $gallery = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $forms = $this->service->createExtension('acme', 'forms-pro', 'Forms Pro');

        $this->assertNotSame($gallery->id, $forms->id);
        $this->assertSame('acme/gallery-pro', $gallery->identifier());
        $this->assertSame('acme/forms-pro', $forms->identifier());
    }

    public function test_malformed_vendor_slug_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createExtension('ACME', 'gallery-pro', 'Gallery Pro');
    }

    public function test_malformed_package_slug_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createExtension('acme', 'gallery_pro', 'Gallery Pro');
    }

    // ── release creation ────────────────────────────────────────────────────

    public function test_create_1_0_0_release(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->assertSame('1.0.0', $release->version);
        $this->assertSame(1, $release->sdk_contract_version);
        $this->assertSame(ExtensionRelease::STATUS_PUBLISHED, $release->status);
        $this->assertNotNull($release->published_at);
    }

    public function test_current_release_belongs_to_extension(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $extension->refresh();
        $this->assertSame($release->id, $extension->current_release_id);
        $this->assertTrue($release->isCurrent());
    }

    public function test_duplicate_version_rejected(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($extension->fresh(), '1.0.0', 1, $this->manifest());
    }

    public function test_create_1_1_0_release_supersedes_the_prior_current_release(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $first = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $second = $this->service->release($extension->fresh(), '1.1.0', 1, $this->manifest());

        $first->refresh();
        $extension->refresh();
        $this->assertSame(ExtensionRelease::STATUS_SUPERSEDED, $first->status);
        $this->assertSame(ExtensionRelease::STATUS_PUBLISHED, $second->status);
        $this->assertSame($second->id, $extension->current_release_id);
        $this->assertCount(2, $extension->releases()->get());
    }

    public function test_representative_valid_versions_are_accepted(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        foreach (['1.0.0', '1.2.3', '2.0.0'] as $i => $version) {
            $release = $this->service->release($extension->fresh(), $version, 1, $this->manifest(['n' => $i]));
            $this->assertSame($version, $release->version);
        }
    }

    public function test_malformed_versions_are_rejected(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        foreach (['1.0', 'v1.0.0', '1.0.0-beta', 'not-a-version', ''] as $version) {
            try {
                $this->service->release($extension->fresh(), $version, 1, $this->manifest());
                $this->fail("expected version \"{$version}\" to be rejected");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('semver', $e->getMessage());
            }
        }
    }

    public function test_invalid_sdk_contract_version_is_rejected(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($extension, '1.0.0', 0, $this->manifest());
    }

    public function test_empty_manifest_is_rejected(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($extension, '1.0.0', 1, []);
    }

    public function test_list_shaped_manifest_is_rejected(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($extension, '1.0.0', 1, ['a', 'b', 'c']);
    }

    // ── cross-extension current-release pointer protection ─────────────────

    public function test_cross_extension_current_release_pointer_is_rejected(): void
    {
        $gallery = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($gallery, '1.0.0', 1, $this->manifest());

        $forms = $this->service->createExtension('acme', 'forms-pro', 'Forms Pro');
        $formsRelease = $this->service->release($forms->fresh(), '1.0.0', 1, $this->manifest());

        // Direct property assignment (bypassing $fillable entirely, exactly
        // like ExtensionReleaseService::release() itself does) so this test
        // proves the model-level guard itself, independent of whatever the
        // app's mass-assignment-discarding setting happens to be.
        $galleryFresh = $gallery->fresh();
        $galleryFresh->current_release_id = $formsRelease->id;

        $this->expectException(RuntimeException::class);
        $galleryFresh->save();
    }

    // ── immutability ─────────────────────────────────────────────────────────

    public function test_published_release_content_is_immutable(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->expectException(RuntimeException::class);
        $release->update(['version' => '9.9.9']);
    }

    public function test_manifest_is_immutable_after_publish(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->expectException(RuntimeException::class);
        $release->update(['manifest' => ['elements' => []]]);
    }

    public function test_sdk_contract_version_is_immutable_after_publish(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->expectException(RuntimeException::class);
        $release->update(['sdk_contract_version' => 2]);
    }

    public function test_status_alone_may_still_transition(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $release->update(['status' => ExtensionRelease::STATUS_DEPRECATED]);

        $this->assertSame(ExtensionRelease::STATUS_DEPRECATED, $release->fresh()->status);
    }

    public function test_vendor_slug_immutable_once_a_release_exists(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->expectException(RuntimeException::class);
        $extension->fresh()->update(['vendor_slug' => 'other-vendor']);
    }

    public function test_display_name_remains_editable_after_release(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $extension->fresh()->update(['name' => 'Gallery Pro (renamed display name)']);

        $this->assertSame('Gallery Pro (renamed display name)', $extension->fresh()->name);
    }

    // ── delete-protection / historical resolvability ────────────────────────

    public function test_published_release_cannot_be_ordinary_deleted(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest());

        $this->expectException(RuntimeException::class);
        $release->delete();
    }

    public function test_historical_superseded_release_remains_resolvable(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $first = $this->service->release($extension, '1.0.0', 1, $this->manifest());
        $this->service->release($extension->fresh(), '1.1.0', 1, $this->manifest());

        $stillThere = ExtensionRelease::find($first->id);
        $this->assertNotNull($stillThere);
        $this->assertSame(ExtensionRelease::STATUS_SUPERSEDED, $stillThere->status);
        $this->assertSame('1.0.0', $stillThere->version);
    }

    // ── Product domain vocabulary ────────────────────────────────────────────

    public function test_product_type_extension_constant_is_recognized(): void
    {
        $this->assertSame('extension', Product::TYPE_EXTENSION);

        $product = Product::create([
            'sku' => 'ext-acme-gallery-pro',
            'name_fa' => 'افزونه گالری',
            'name_en' => 'Gallery Pro Extension',
            'type' => Product::TYPE_EXTENSION,
            'price_irr' => 0,
            'is_one_time' => true,
            'is_active' => true,
        ]);

        $this->assertSame(Product::TYPE_EXTENSION, $product->type);
        $this->assertIsString($product->typeLabel());
    }
}
