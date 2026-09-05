<?php

namespace Tests\Feature\ThemeBuilder\Dependency;

use App\Models\Extension;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
use App\Services\ThemeBuilder\Dependency\ExtensionVersionSelectionResolver;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionBlockTypeExtractor;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionDependencyExtractor;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use Tests\TestCase;

/**
 * Phase 3G-B-3 — end-to-end dependency extraction (block-type scan +
 * ownership resolution → canonical, deduplicated, deterministic Extension
 * dependency proposals).
 *
 * Phase 3G-B-4 — additionally proves version-constraint truthfulness: a
 * product only ever appears in resolvedDependencies once BOTH its ownership
 * AND its authoring-selected Extension release version are truthfully
 * resolved; the constraint is always the exact "=X.Y.Z" the author actually
 * selected, never a guess, never a range, never null-by-default.
 */
class ThemeExtensionDependencyExtractorTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $releaseService;
    private ThemeExtensionDependencyExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ExtensionReleaseService::class);
        $this->extractor = new ThemeExtensionDependencyExtractor(
            new ThemeExtensionBlockTypeExtractor(),
            new ExtensionElementOwnershipResolver(),
            new ExtensionVersionSelectionResolver(),
        );
    }

    private function publish(Extension $extension, string $version, array $elementTypes): void
    {
        $this->releaseService->release($extension, $version, 1, [
            'elements' => array_map(fn ($t) => ['type' => $t, 'label' => 'L', 'category' => 'general'], $elementTypes),
        ]);
    }

    private function page(array $blocks): array
    {
        return ['home' => ['blocks' => $blocks]];
    }

    private function selection(string $productIdentity, string $version): array
    {
        return [$productIdentity => ['version' => $version]];
    }

    public function test_product_dependencies_are_deduplicated(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:card', 'ext:acme:grid']);

        $pages = $this->page([
            ['type' => 'ext:acme:card', 'id' => 'a'],
            ['type' => 'ext:acme:grid', 'id' => 'b'],
            ['type' => 'ext:acme:card', 'id' => 'c'],
        ]);

        $result = $this->extractor->extractFromSnapshot([], [], $pages, $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame('acme/gallery-pro', $result->resolvedDependencies[0]->productIdentity);
        $this->assertSame(['ext:acme:card', 'ext:acme:grid'], $result->resolvedDependencies[0]->elementTypes);
    }

    public function test_ordering_is_deterministic_lexical_by_product_identity(): void
    {
        $bar = $this->releaseService->createExtension('bar', 'forms-plus', 'Forms Plus');
        $this->publish($bar, '1.0.0', ['ext:bar:form']);

        $acme = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($acme, '1.0.0', ['ext:acme:gallery']);

        $foo = $this->releaseService->createExtension('foo', 'maps', 'Maps');
        $this->publish($foo, '1.0.0', ['ext:foo:map']);

        $pages = $this->page([
            ['type' => 'ext:bar:form', 'id' => 'a'],
            ['type' => 'ext:acme:gallery', 'id' => 'b'],
            ['type' => 'ext:foo:map', 'id' => 'c'],
        ]);

        $selections = array_merge(
            $this->selection('bar/forms-plus', '1.0.0'),
            $this->selection('acme/gallery-pro', '1.0.0'),
            $this->selection('foo/maps', '1.0.0'),
        );

        $result = $this->extractor->extractFromSnapshot([], [], $pages, $selections);

        $identities = array_map(fn ($d) => $d->productIdentity, $result->resolvedDependencies);
        $this->assertSame(['acme/gallery-pro', 'bar/forms-plus', 'foo/maps'], $identities);
    }

    public function test_dependency_identifier_is_the_canonical_vendor_package_form(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertSame('acme/gallery-pro', $result->resolvedDependencies[0]->productIdentity);
        $this->assertTrue(ExtensionProductIdentity::isValidIdentifier($result->resolvedDependencies[0]->productIdentity));
    }

    public function test_dependency_type_is_exactly_extension(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $exported = $result->resolvedDependencies[0]->toReleaseDependencyArray();
        $this->assertSame(ThemeReleaseDependency::TYPE_EXTENSION, $exported['type']);
        $this->assertSame('extension', $exported['type']);
    }

    public function test_unresolved_type_is_propagated(): void
    {
        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:nobody:owns_this', 'id' => 'a'],
        ]));

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertSame(['ext:nobody:owns_this'], $result->unresolvedElementTypes);
        $this->assertTrue($result->hasUnresolved());
        $this->assertFalse($result->isFullyResolved());
    }

    public function test_ambiguous_type_is_propagated(): void
    {
        // ADR-046 (Phase 3G-B-3 ownership closure) now permanently forbids
        // ExtensionReleaseService::release() from letting two products claim
        // the same type — seeded via direct insert to simulate legacy/
        // manual/imported data that pre-dates that invariant, exactly as
        // the resolver's retained ambiguity handling exists to defend
        // against (ADR-046 point 12).
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

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:widget', 'id' => 'a'],
        ]));

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertTrue($result->hasAmbiguous());
        $this->assertSame(['acme/gallery-pro', 'acme/widgets-plus'], $result->ambiguousElementTypes['ext:acme:widget']);
        $this->assertNull($result->ownershipMap['ext:acme:widget']);
    }

    public function test_no_product_identity_is_ever_guessed_from_the_ext_type_string(): void
    {
        // ext:acme:gallery superficially "looks like" it might belong to
        // acme/gallery — but no Extension named acme/gallery was ever
        // published, only acme/gallery-pro. The resolver must never infer
        // the package slug from the block name.
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertSame('acme/gallery-pro', $result->resolvedDependencies[0]->productIdentity);
        $this->assertNotSame('acme/gallery', $result->resolvedDependencies[0]->productIdentity);
    }

    public function test_no_package_slug_is_guessed_when_nothing_publishes_the_type(): void
    {
        // No Extension exists at all — proves the resolver reports
        // unresolved rather than fabricating "acme/gallery" from the type.
        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]));

        $this->assertSame(['ext:acme:gallery'], $result->unresolvedElementTypes);
    }

    public function test_unrelated_dependency_type_constants_are_untouched(): void
    {
        $this->assertSame('module', ThemeReleaseDependency::TYPE_MODULE);
        $this->assertSame('motion_asset', ThemeReleaseDependency::TYPE_MOTION_ASSET);
        $this->assertSame('extension', ThemeReleaseDependency::TYPE_EXTENSION);
    }

    public function test_extracted_dependency_is_required_by_v1_default(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertTrue($result->resolvedDependencies[0]->isRequired);
    }

    public function test_no_blocks_at_all_yields_the_empty_result(): void
    {
        $result = $this->extractor->extractFromSnapshot([], [], []);

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertSame([], $result->unresolvedElementTypes);
        $this->assertSame([], $result->ambiguousElementTypes);
        $this->assertTrue($result->isFullyResolved());
    }

    // ── version-selection truthfulness (Phase 3G-B-4) ───────────────────────

    public function test_a_used_extension_with_no_selection_is_unresolved_version_not_a_guess(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '2.5.1', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]));

        // No selection given — must NOT appear in resolvedDependencies at all.
        $this->assertSame([], $result->resolvedDependencies);
        $this->assertSame(['acme/gallery-pro'], $result->unresolvedVersionSelections);
        $this->assertSame([], $result->invalidVersionSelections);
        $this->assertFalse($result->isFullyResolvedWithVersions());
        // Ownership itself is still fully resolved — only the version is not.
        $this->assertTrue($result->isFullyResolved());
    }

    public function test_selected_version_produces_the_exact_version_constraint(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '2.3.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '2.3.0'));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame('=2.3.0', $result->resolvedDependencies[0]->versionConstraint);
    }

    public function test_no_broader_constraint_is_ever_produced(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '2.5.1', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '2.5.1'));

        $constraint = $result->resolvedDependencies[0]->versionConstraint;
        $this->assertSame('=2.5.1', $constraint);
        $this->assertNotSame('*', $constraint);
        $this->assertNotSame('latest', $constraint);
        $this->assertNotSame('current', $constraint);
        $this->assertNotSame('>=2.5.1', $constraint);
        $this->assertNotSame('^2.5.1', $constraint);
    }

    public function test_selection_for_wrong_extension_version_is_invalid_and_fails_closed(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '9.9.9'));

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertArrayHasKey('acme/gallery-pro', $result->invalidVersionSelections);
        $this->assertSame([], $result->unresolvedVersionSelections);
    }

    public function test_selected_release_not_containing_the_used_element_is_invalid(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);
        $this->publish($extension->fresh(), '2.0.0', ['ext:acme:other']);

        // Theme uses ext:acme:gallery (owned historically by 1.0.0), but
        // authoring selected 2.0.0 — whose manifest does not provide it.
        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '2.0.0'));

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertArrayHasKey('acme/gallery-pro', $result->invalidVersionSelections);
    }

    public function test_current_release_id_changing_does_not_alter_an_existing_selection(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);
        $extension->refresh();
        $pinnedVersion = '1.0.0';

        // current_release_id moves on to 2.0.0, which no longer provides the type.
        $this->publish($extension->fresh(), '2.0.0', ['ext:acme:other']);
        $extension->refresh();
        $this->assertNotSame($pinnedVersion, $extension->currentRelease->version);

        // The Theme's OWN recorded selection still pins 1.0.0 — must still resolve truthfully.
        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', $pinnedVersion));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame('=1.0.0', $result->resolvedDependencies[0]->versionConstraint);
    }

    public function test_superseded_selected_release_remains_resolvable(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);
        $this->publish($extension->fresh(), '2.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame('=1.0.0', $result->resolvedDependencies[0]->versionConstraint);
    }

    public function test_deprecated_selected_release_remains_resolvable(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->releaseService->release($extension, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);
        $release->update(['status' => \App\Models\ExtensionRelease::STATUS_DEPRECATED]);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame('=1.0.0', $result->resolvedDependencies[0]->versionConstraint);
    }

    public function test_two_elements_same_product_yields_one_selection_one_dependency(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:card', 'ext:acme:grid']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:card', 'id' => 'a'],
            ['type' => 'ext:acme:grid', 'id' => 'b'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame(['ext:acme:card', 'ext:acme:grid'], $result->resolvedDependencies[0]->elementTypes);
    }

    public function test_two_extensions_have_independent_selections(): void
    {
        $acme = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($acme, '2.3.0', ['ext:acme:gallery']);
        $foo = $this->releaseService->createExtension('foo', 'maps', 'Maps');
        $this->publish($foo, '1.4.2', ['ext:foo:map']);

        $selections = array_merge(
            $this->selection('acme/gallery-pro', '2.3.0'),
            $this->selection('foo/maps', '1.4.2'),
        );

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
            ['type' => 'ext:foo:map', 'id' => 'b'],
        ]), $selections);

        $byProduct = [];
        foreach ($result->resolvedDependencies as $dependency) {
            $byProduct[$dependency->productIdentity] = $dependency->versionConstraint;
        }

        $this->assertSame(['acme/gallery-pro' => '=2.3.0', 'foo/maps' => '=1.4.2'], $byProduct);
    }

    public function test_unused_selection_does_not_become_a_dependency(): void
    {
        $unused = $this->releaseService->createExtension('bar', 'unused-thing', 'Unused Thing');
        $this->publish($unused, '1.0.0', ['ext:bar:thing']);

        $result = $this->extractor->extractFromSnapshot([], [], [], $this->selection('bar/unused-thing', '1.0.0'));

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertSame(['bar/unused-thing'], $result->unusedSelections);
    }

    public function test_ownership_ambiguity_is_independent_of_version_selection(): void
    {
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

        // A version selection is provided, but ownership is still ambiguous
        // — must not resolve just because a plausible-looking selection exists.
        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:widget', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertSame([], $result->resolvedDependencies);
        $this->assertTrue($result->hasAmbiguous());
    }

    public function test_unresolved_ownership_is_independent_of_version_selection(): void
    {
        // A selection exists for a product that owns nothing the Theme uses.
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:nobody:owns_this', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertSame(['ext:nobody:owns_this'], $result->unresolvedElementTypes);
        $this->assertSame([], $result->resolvedDependencies);
        // The unused (for this Theme) acme/gallery-pro selection is reported, not silently dropped.
        $this->assertSame(['acme/gallery-pro'], $result->unusedSelections);
    }

    public function test_two_products_sharing_the_same_version_string_never_cross_attribute(): void
    {
        // Both products happen to have a "1.0.0" release — proves the
        // selection lookup is always scoped to the correct product, never
        // able to accidentally resolve a different product's release.
        $acme = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($acme, '1.0.0', ['ext:acme:gallery']);
        $foo = $this->releaseService->createExtension('foo', 'maps', 'Maps');
        $this->publish($foo, '1.0.0', ['ext:foo:map']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]), $this->selection('acme/gallery-pro', '1.0.0'));

        $this->assertCount(1, $result->resolvedDependencies);
        $this->assertSame('acme/gallery-pro', $result->resolvedDependencies[0]->productIdentity);
    }
}
