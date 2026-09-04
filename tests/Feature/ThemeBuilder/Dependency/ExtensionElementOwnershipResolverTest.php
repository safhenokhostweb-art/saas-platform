<?php

namespace Tests\Feature\ThemeBuilder\Dependency;

use App\Models\Extension;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3G-B-3 — ExtensionElementOwnershipResolver is the ONLY authority
 * allowed to map an `ext:*` element type to its owning Extension PRODUCT.
 * These tests prove it never infers from the type string, never guesses on
 * ambiguity, and honors historical-release ownership evidence.
 */
class ExtensionElementOwnershipResolverTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $releaseService;
    private ExtensionElementOwnershipResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ExtensionReleaseService::class);
        $this->resolver = new ExtensionElementOwnershipResolver();
    }

    private function manifest(array $elements): array
    {
        return ['elements' => $elements];
    }

    private function element(string $type): array
    {
        return ['type' => $type, 'label' => 'Label', 'category' => 'general'];
    }

    private function publish(Extension $extension, string $version, array $elementTypes): void
    {
        $this->releaseService->release(
            $extension,
            $version,
            1,
            $this->manifest(array_map(fn ($t) => $this->element($t), $elementTypes))
        );
    }

    public function test_one_type_resolves_to_exactly_one_extension_product(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $results = $this->resolver->resolve(['ext:acme:gallery']);

        $this->assertTrue($results['ext:acme:gallery']->isResolved());
        $this->assertSame('acme/gallery-pro', $results['ext:acme:gallery']->productIdentity);
    }

    public function test_two_types_from_the_same_product_both_resolve_to_it(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery', 'ext:acme:grid']);

        $results = $this->resolver->resolve(['ext:acme:gallery', 'ext:acme:grid']);

        $this->assertSame('acme/gallery-pro', $results['ext:acme:gallery']->productIdentity);
        $this->assertSame('acme/gallery-pro', $results['ext:acme:grid']->productIdentity);
    }

    public function test_two_types_from_different_products_resolve_independently(): void
    {
        $gallery = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($gallery, '1.0.0', ['ext:acme:gallery']);

        $maps = $this->releaseService->createExtension('foo', 'maps', 'Maps');
        $this->publish($maps, '1.0.0', ['ext:foo:map']);

        $results = $this->resolver->resolve(['ext:acme:gallery', 'ext:foo:map']);

        $this->assertSame('acme/gallery-pro', $results['ext:acme:gallery']->productIdentity);
        $this->assertSame('foo/maps', $results['ext:foo:map']->productIdentity);
    }

    public function test_zero_manifest_owner_is_unresolved(): void
    {
        $results = $this->resolver->resolve(['ext:nobody:owns_this']);

        $this->assertTrue($results['ext:nobody:owns_this']->isUnresolved());
        $this->assertNull($results['ext:nobody:owns_this']->productIdentity);
        $this->assertSame([], $results['ext:nobody:owns_this']->candidateProductIdentities);
    }

    public function test_multiple_manifest_owners_is_ambiguous_and_never_arbitrarily_picked(): void
    {
        // ADR-046 (Phase 3G-B-3 ownership closure) now permanently forbids
        // ExtensionReleaseService::release() from ever letting two products
        // claim the same type — so this scenario is seeded via direct
        // query-builder inserts, exactly as real legacy/manual/imported data
        // that pre-dates that invariant would look. The resolver's ambiguity
        // handling is retained specifically as defense-in-depth for data
        // like this (ADR-046 point 12) — it must still detect it.
        $one = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->releaseService->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        foreach ([$one, $two] as $extension) {
            DB::table('extension_releases')->insert([
                'extension_id' => $extension->id,
                'version' => '1.0.0',
                'sdk_contract_version' => 1,
                'manifest' => json_encode([
                    'product_identity' => $extension->identifier(),
                    'package_version' => '1.0.0',
                    'sdk_contract_version' => 1,
                    'elements' => [['type' => 'ext:acme:widget', 'label' => 'Widget', 'category' => 'general']],
                ]),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $results = $this->resolver->resolve(['ext:acme:widget']);

        $this->assertTrue($results['ext:acme:widget']->isAmbiguous());
        $this->assertNull($results['ext:acme:widget']->productIdentity);
        $this->assertSame(['acme/gallery-pro', 'acme/widgets-plus'], $results['ext:acme:widget']->candidateProductIdentities);
    }

    public function test_historical_release_still_provides_ownership_evidence(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:legacy_grid']);
        // 2.0.0 supersedes 1.0.0 and drops ext:acme:legacy_grid entirely.
        $this->publish($extension->fresh(), '2.0.0', ['ext:acme:new_grid']);

        $results = $this->resolver->resolve(['ext:acme:legacy_grid', 'ext:acme:new_grid']);

        $this->assertTrue($results['ext:acme:legacy_grid']->isResolved());
        $this->assertSame('acme/gallery-pro', $results['ext:acme:legacy_grid']->productIdentity);
        $this->assertSame('acme/gallery-pro', $results['ext:acme:new_grid']->productIdentity);
    }

    public function test_changing_current_release_id_does_not_falsely_reassign_historical_ownership(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:legacy_grid']);
        $extension->refresh();
        $firstReleaseId = $extension->current_release_id;

        $this->publish($extension->fresh(), '2.0.0', ['ext:acme:new_grid']);
        $extension->refresh();

        $this->assertNotSame($firstReleaseId, $extension->current_release_id);

        // Ownership of the now-historical element type must be unchanged.
        $results = $this->resolver->resolve(['ext:acme:legacy_grid']);
        $this->assertSame('acme/gallery-pro', $results['ext:acme:legacy_grid']->productIdentity);
    }

    public function test_invalid_manifest_is_not_treated_as_a_valid_authority(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        // Bypass ExtensionReleaseService/ExtensionManifest validation entirely
        // (direct query-builder insert) to simulate a hypothetically
        // corrupted row — the resolver must fail closed, not crash, and
        // must not count this as ownership evidence.
        DB::table('extension_releases')->insert([
            'extension_id' => $extension->id,
            'version' => '1.0.0',
            'sdk_contract_version' => 1,
            'manifest' => json_encode(['this' => 'is not a valid manifest shape at all']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->resolver->resolve(['ext:acme:whatever']);

        $this->assertTrue($results['ext:acme:whatever']->isUnresolved());
    }

    public function test_unrequested_element_types_are_never_returned(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery', 'ext:acme:grid']);

        $results = $this->resolver->resolve(['ext:acme:gallery']);

        $this->assertArrayHasKey('ext:acme:gallery', $results);
        $this->assertArrayNotHasKey('ext:acme:grid', $results);
        $this->assertCount(1, $results);
    }
}
