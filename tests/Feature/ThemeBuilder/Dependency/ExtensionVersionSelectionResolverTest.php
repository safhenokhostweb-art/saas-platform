<?php

namespace Tests\Feature\ThemeBuilder\Dependency;

use App\Services\ThemeBuilder\Dependency\ExtensionVersionSelectionResolver;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3G-B-4 — isolated tests of ExtensionVersionSelectionResolver, the
 * sole authority for "what Extension release version did Theme authoring
 * truthfully select, and can it be trusted?" No compatibility range is ever
 * produced — exact version only — and no selection is ever silently
 * substituted with the current release or a wildcard.
 */
class ExtensionVersionSelectionResolverTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $releaseService;
    private ExtensionVersionSelectionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ExtensionReleaseService::class);
        $this->resolver = new ExtensionVersionSelectionResolver();
    }

    private function publish(string $vendor, string $package, string $version, array $elementTypes): \App\Models\Extension
    {
        $extension = $this->releaseService->createExtension($vendor, $package, ucfirst($package));
        $this->releaseService->release($extension, $version, 1, [
            'elements' => array_map(fn ($t) => ['type' => $t, 'label' => 'L', 'category' => 'general'], $elementTypes),
        ]);

        return $extension->fresh();
    }

    public function test_valid_selection_resolves_with_exact_product_and_version_preserved(): void
    {
        $this->publish('acme', 'gallery-pro', '2.3.0', ['ext:acme:gallery']);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '2.3.0'],
            ['acme/gallery-pro' => ['ext:acme:gallery']]
        );

        $result = $results['acme/gallery-pro'];
        $this->assertTrue($result->isResolved());
        $this->assertSame('acme/gallery-pro', $result->productIdentity);
        $this->assertSame('2.3.0', $result->selectedVersion);
        $this->assertSame('=2.3.0', $result->versionConstraint);
    }

    public function test_no_selection_is_unresolved(): void
    {
        $this->publish('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);

        $results = $this->resolver->resolve([], ['acme/gallery-pro' => ['ext:acme:gallery']]);

        $this->assertTrue($results['acme/gallery-pro']->isUnresolved());
        $this->assertNull($results['acme/gallery-pro']->versionConstraint);
    }

    public function test_nonexistent_selected_version_is_invalid(): void
    {
        $this->publish('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '9.9.9'],
            ['acme/gallery-pro' => ['ext:acme:gallery']]
        );

        $this->assertTrue($results['acme/gallery-pro']->isInvalid());
        $this->assertSame('9.9.9', $results['acme/gallery-pro']->selectedVersion);
        $this->assertNull($results['acme/gallery-pro']->versionConstraint);
    }

    public function test_nonexistent_extension_product_is_invalid(): void
    {
        $results = $this->resolver->resolve(
            ['acme/does-not-exist' => '1.0.0'],
            ['acme/does-not-exist' => ['ext:acme:gallery']]
        );

        $this->assertTrue($results['acme/does-not-exist']->isInvalid());
    }

    public function test_selected_release_not_containing_a_used_element_is_invalid(): void
    {
        $this->publish('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '1.0.0'],
            ['acme/gallery-pro' => ['ext:acme:gallery', 'ext:acme:not_in_this_release']]
        );

        $this->assertTrue($results['acme/gallery-pro']->isInvalid());
        $this->assertStringContainsString('ext:acme:not_in_this_release', $results['acme/gallery-pro']->invalidReason);
    }

    public function test_no_wildcard_or_latest_fallback_is_ever_produced(): void
    {
        $this->publish('acme', 'gallery-pro', '2.3.0', ['ext:acme:gallery']);

        $resolved = $this->resolver->resolve(['acme/gallery-pro' => '2.3.0'], ['acme/gallery-pro' => ['ext:acme:gallery']])['acme/gallery-pro'];
        $unresolved = $this->resolver->resolve([], ['acme/gallery-pro' => ['ext:acme:gallery']])['acme/gallery-pro'];

        foreach ([$resolved->versionConstraint, $unresolved->versionConstraint] as $constraint) {
            $this->assertNotSame('*', $constraint);
            $this->assertNotSame('latest', $constraint);
            $this->assertNotSame('current', $constraint);
        }
        $this->assertNull($unresolved->versionConstraint);
    }

    public function test_superseded_release_selection_still_resolves(): void
    {
        $extension = $this->publish('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $this->releaseService->release($extension->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '1.0.0'],
            ['acme/gallery-pro' => ['ext:acme:gallery']]
        );

        $this->assertTrue($results['acme/gallery-pro']->isResolved());
        $this->assertSame('=1.0.0', $results['acme/gallery-pro']->versionConstraint);
    }

    public function test_deprecated_release_selection_still_resolves(): void
    {
        $extension = $this->publish('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $release = $extension->currentRelease;
        $release->update(['status' => \App\Models\ExtensionRelease::STATUS_DEPRECATED]);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '1.0.0'],
            ['acme/gallery-pro' => ['ext:acme:gallery']]
        );

        $this->assertTrue($results['acme/gallery-pro']->isResolved());
    }

    public function test_two_products_with_identical_version_strings_never_cross_attribute(): void
    {
        $this->publish('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $this->publish('foo', 'maps', '1.0.0', ['ext:foo:map']);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '1.0.0', 'foo/maps' => '1.0.0'],
            ['acme/gallery-pro' => ['ext:acme:gallery'], 'foo/maps' => ['ext:foo:map']]
        );

        $this->assertTrue($results['acme/gallery-pro']->isResolved());
        $this->assertTrue($results['foo/maps']->isResolved());
        $this->assertSame('acme/gallery-pro', $results['acme/gallery-pro']->productIdentity);
        $this->assertSame('foo/maps', $results['foo/maps']->productIdentity);
    }

    public function test_malformed_manifest_on_the_selected_release_is_invalid(): void
    {
        $extension = $this->releaseService->createExtension('acme', 'gallery-pro', 'Gallery Pro');

        \Illuminate\Support\Facades\DB::table('extension_releases')->insert([
            'extension_id' => $extension->id,
            'version' => '1.0.0',
            'sdk_contract_version' => 1,
            'manifest' => json_encode(['not' => 'a valid manifest']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->resolver->resolve(
            ['acme/gallery-pro' => '1.0.0'],
            ['acme/gallery-pro' => ['ext:acme:gallery']]
        );

        $this->assertTrue($results['acme/gallery-pro']->isInvalid());
    }
}
