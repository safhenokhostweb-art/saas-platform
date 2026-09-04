<?php

namespace Tests\Feature\ThemeBuilder\Dependency;

use App\Models\Extension;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
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
 * dependency proposals). Also proves version-constraint truthfulness: this
 * phase has no source of a real Extension version requirement, so it must
 * never guess one.
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

    public function test_product_dependencies_are_deduplicated(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:card', 'ext:acme:grid']);

        $pages = $this->page([
            ['type' => 'ext:acme:card', 'id' => 'a'],
            ['type' => 'ext:acme:grid', 'id' => 'b'],
            ['type' => 'ext:acme:card', 'id' => 'c'],
        ]);

        $result = $this->extractor->extractFromSnapshot([], [], $pages);

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

        $result = $this->extractor->extractFromSnapshot([], [], $pages);

        $identities = array_map(fn ($d) => $d->productIdentity, $result->resolvedDependencies);
        $this->assertSame(['acme/gallery-pro', 'bar/forms-plus', 'foo/maps'], $identities);
    }

    public function test_dependency_identifier_is_the_canonical_vendor_package_form(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]));

        $this->assertSame('acme/gallery-pro', $result->resolvedDependencies[0]->productIdentity);
        $this->assertTrue(ExtensionProductIdentity::isValidIdentifier($result->resolvedDependencies[0]->productIdentity));
    }

    public function test_dependency_type_is_exactly_extension(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]));

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
        ]));

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

    // ── version-constraint truthfulness (section 19-20/36) ──────────────────

    public function test_version_constraint_is_always_null_never_guessed(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '2.5.1', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]));

        $this->assertNull($result->resolvedDependencies[0]->versionConstraint);
        $this->assertNotSame('*', $result->resolvedDependencies[0]->versionConstraint);
        $this->assertNotSame('latest', $result->resolvedDependencies[0]->versionConstraint);
        $this->assertNotSame('>=2.5.1', $result->resolvedDependencies[0]->versionConstraint);
    }

    public function test_extracted_dependency_is_required_by_v1_default(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->publish($extension, '1.0.0', ['ext:acme:gallery']);

        $result = $this->extractor->extractFromSnapshot([], [], $this->page([
            ['type' => 'ext:acme:gallery', 'id' => 'a'],
        ]));

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
}
