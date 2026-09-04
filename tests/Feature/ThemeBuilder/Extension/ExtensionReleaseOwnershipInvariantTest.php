<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\Extension;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 3G-B-3 ownership closure (ADR-046) — proves the durable, global,
 * permanent, non-transferable Extension element-type ownership invariant is
 * enforced at ExtensionReleaseService::release(), and that
 * ExtensionElementOwnershipResolver's ambiguity handling is retained as
 * defense-in-depth for data this ADR did not create (legacy/manual/direct-DB).
 */
class ExtensionReleaseOwnershipInvariantTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ExtensionReleaseService::class);
    }

    private function manifest(array $elementTypes): array
    {
        return ['elements' => array_map(
            fn ($t) => ['type' => $t, 'label' => 'Label', 'category' => 'general'],
            $elementTypes
        )];
    }

    // ── 1-4: same-product lifecycle ─────────────────────────────────────────

    public function test_first_product_can_publish_a_new_element_type(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        $release = $this->service->release($extension, '1.0.0', 1, $this->manifest(['ext:acme:grid']));

        $this->assertSame('1.0.0', $release->version);
    }

    public function test_same_product_can_reuse_the_type_in_its_next_release(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest(['ext:acme:grid']));

        $release = $this->service->release($extension->fresh(), '2.0.0', 1, $this->manifest(['ext:acme:grid']));

        $this->assertSame('2.0.0', $release->version);
    }

    public function test_same_product_can_remove_the_type_in_a_later_release(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest(['ext:acme:grid']));

        $release = $this->service->release($extension->fresh(), '2.0.0', 1, $this->manifest(['ext:acme:other']));

        $this->assertSame('2.0.0', $release->version);
    }

    public function test_same_product_can_reintroduce_the_type_after_removing_it(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
        $this->service->release($extension->fresh(), '2.0.0', 1, $this->manifest(['ext:acme:other']));

        $release = $this->service->release($extension->fresh(), '3.0.0', 1, $this->manifest(['ext:acme:grid']));

        $this->assertSame('3.0.0', $release->version);
    }

    // ── 5-8: cross-product rejection, regardless of status/current-release ──

    public function test_second_product_cannot_publish_a_historically_owned_type(): void
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($one, '1.0.0', 1, $this->manifest(['ext:acme:grid']));

        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('acme/gallery-pro');
        $this->service->release($two, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
    }

    public function test_rejection_occurs_even_when_the_declaring_release_is_superseded(): void
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($one, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
        // 2.0.0 supersedes 1.0.0 and no longer declares the type.
        $this->service->release($one->fresh(), '2.0.0', 1, $this->manifest(['ext:acme:other']));

        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($two, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
    }

    public function test_rejection_occurs_even_when_the_declaring_release_is_deprecated(): void
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $firstRelease = $this->service->release($one, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
        $firstRelease->update(['status' => \App\Models\ExtensionRelease::STATUS_DEPRECATED]);

        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($two, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
    }

    public function test_rejection_occurs_even_when_the_original_products_current_release_no_longer_contains_it(): void
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($one, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
        $one->refresh();
        $this->service->release($one->fresh(), '2.0.0', 1, $this->manifest(['ext:acme:other']));
        $one->refresh();

        // Confirm the CURRENT release genuinely no longer declares the type.
        $this->assertSame('2.0.0', $one->currentRelease->version);
        $currentTypes = array_column($one->currentRelease->manifest['elements'], 'type');
        $this->assertNotContains('ext:acme:grid', $currentTypes);

        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($two, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
    }

    // ── 9-10: independence and per-element-in-one-manifest checking ─────────

    public function test_different_element_types_across_products_remain_allowed(): void
    {
        $gallery = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($gallery, '1.0.0', 1, $this->manifest(['ext:acme:gallery']));

        $maps = $this->service->createExtension('foo', 'maps', 'Maps');
        $release = $this->service->release($maps, '1.0.0', 1, $this->manifest(['ext:foo:map']));

        $this->assertSame('1.0.0', $release->version);
    }

    public function test_multiple_elements_in_one_manifest_are_checked_independently(): void
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($one, '1.0.0', 1, $this->manifest(['ext:acme:owned']));

        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        // ext:acme:owned conflicts; ext:acme:brand_new does not — the whole
        // manifest must still be rejected (see next test for atomicity).
        $this->expectException(InvalidArgumentException::class);
        $this->service->release($two, '1.0.0', 1, $this->manifest(['ext:acme:brand_new', 'ext:acme:owned']));
    }

    // ── 11: atomicity — one conflicting element blocks the whole release ────

    public function test_publication_is_atomic_one_conflicting_element_means_no_partial_release(): void
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($one, '1.0.0', 1, $this->manifest(['ext:acme:owned']));

        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        try {
            $this->service->release($two, '1.0.0', 1, $this->manifest(['ext:acme:brand_new', 'ext:acme:owned']));
            $this->fail('expected the conflicting release to be rejected');
        } catch (InvalidArgumentException) {
            // expected
        }

        $two->refresh();
        $this->assertSame(0, $two->releases()->count());
        $this->assertNull($two->current_release_id);

        // The conflicting type must also not have been claimed by product two.
        $resolver = new ExtensionElementOwnershipResolver();
        $results = $resolver->resolve(['ext:acme:brand_new']);
        $this->assertTrue($results['ext:acme:brand_new']->isUnresolved());
    }

    // ── 12-13: malformed/ambiguous historical data (defense-in-depth) ───────

    public function test_malformed_historical_manifest_does_not_become_false_ownership_evidence(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        DB::table('extension_releases')->insert([
            'extension_id' => $extension->id,
            'version' => '0.0.1',
            'sdk_contract_version' => 1,
            'manifest' => json_encode(['not' => 'a valid manifest shape']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Same vendor, different package (ext:acme:* may only ever be
        // declared by an acme/* product — an unrelated, pre-existing
        // ExtensionManifest vendor-match rule, not part of this ADR).
        $other = $this->service->createExtension('acme', 'other-package', 'Other Package');

        // The malformed row must not block a genuinely different product
        // from claiming ext:acme:grid — it contributes zero evidence.
        $release = $this->service->release($other, '1.0.0', 1, $this->manifest(['ext:acme:grid']));
        $this->assertSame('1.0.0', $release->version);
    }

    public function test_resolver_still_returns_ambiguous_for_a_deliberately_seeded_invalid_historical_collision(): void
    {
        // Simulate legacy/manual/imported data that pre-dates ADR-046's
        // enforcement — bypasses ExtensionReleaseService entirely via direct
        // inserts, exactly as real "existing ambiguous data" would look.
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        foreach ([$one, $two] as $extension) {
            DB::table('extension_releases')->insert([
                'extension_id' => $extension->id,
                'version' => '1.0.0',
                'sdk_contract_version' => 1,
                'manifest' => json_encode([
                    'product_identity' => $extension->identifier(),
                    'package_version' => '1.0.0',
                    'sdk_contract_version' => 1,
                    'elements' => [['type' => 'ext:acme:legacy_collision', 'label' => 'L', 'category' => 'general']],
                ]),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $resolver = new ExtensionElementOwnershipResolver();
        $results = $resolver->resolve(['ext:acme:legacy_collision']);

        $this->assertTrue($results['ext:acme:legacy_collision']->isAmbiguous());
        $this->assertSame(['acme/gallery-pro', 'acme/widgets-plus'], $results['ext:acme:legacy_collision']->candidateProductIdentities);
    }

    // ── 14: historical ownership survives current-release changes ──────────

    public function test_historical_owner_remains_resolvable_after_current_release_changes(): void
    {
        $extension = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($extension, '1.0.0', 1, $this->manifest(['ext:acme:legacy']));
        $this->service->release($extension->fresh(), '2.0.0', 1, $this->manifest(['ext:acme:new']));

        $resolver = new ExtensionElementOwnershipResolver();
        $results = $resolver->resolve(['ext:acme:legacy']);

        $this->assertTrue($results['ext:acme:legacy']->isResolved());
        $this->assertSame('acme/gallery-pro', $results['ext:acme:legacy']->productIdentity);
    }

    // ── concurrency primitive proof ──────────────────────────────────────────

    public function test_the_global_ownership_lock_primitive_provides_real_mutual_exclusion(): void
    {
        // Proves the underlying Cache::lock() primitive itself (the exact
        // mechanism ExtensionReleaseService::release() wraps its critical
        // section in) genuinely serializes: while one caller holds the lock,
        // a second caller cannot also acquire it. A true two-process race of
        // release() itself is outside what a single PHPUnit process can
        // orchestrate — this proves the correctness-relevant primitive.
        $lockKey = 'extension-release-element-ownership-publish-lock';

        $outerLock = Cache::lock($lockKey, 10);
        $this->assertTrue($outerLock->get());

        try {
            $innerLock = Cache::lock($lockKey, 10);
            $this->assertFalse($innerLock->get(), 'a second caller must not be able to acquire the lock while the first holds it');
        } finally {
            $outerLock->release();
        }

        // Once released, a new acquisition attempt must succeed again.
        $afterRelease = Cache::lock($lockKey, 10);
        $this->assertTrue($afterRelease->get());
        $afterRelease->release();
    }
}
