<?php

namespace Tests\Unit\ExtensionSdk;

use SaaSPlatform\ExtensionSdk\ExtensionControlDefinition;
use SaaSPlatform\ExtensionSdk\ExtensionElementDefinition;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use SaaSPlatform\ExtensionSdk\ExtensionSdkContract;
use SaaSPlatform\ExtensionSdk\ExtensionSdkException;
use Tests\TestCase;

/**
 * Phase 3G-B-2 (ADR-045 points 5, 10, 11) — the canonical Extension
 * declarative manifest contract test. This is the ONE authority that
 * replaces ExtensionReleaseService's prior structural-only (non-empty,
 * non-list) manifest check; see ExtensionReleaseServiceTest for the
 * integration-level proof that the service actually uses it.
 */
class ExtensionManifestTest extends TestCase
{
    private function product(): ExtensionProductIdentity
    {
        return ExtensionProductIdentity::fromParts('acme', 'gallery-pro');
    }

    private function element(string $type = 'ext:acme:gallery', array $controls = []): ExtensionElementDefinition
    {
        return ExtensionElementDefinition::make(
            type: $type,
            label: 'Gallery',
            category: 'media',
            controls: $controls,
        );
    }

    // ── product identity ─────────────────────────────────────────────────────

    public function test_valid_product_identity_is_accepted(): void
    {
        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$this->element()]);

        $this->assertSame('acme/gallery-pro', $manifest->productIdentity->toString());
    }

    public function test_invalid_product_identity_string_is_rejected_via_from_array(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::fromArray([
            'product_identity' => 'Not Valid',
            'package_version' => '1.0.0',
            'sdk_contract_version' => 1,
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'media']],
        ]);
    }

    // ── semver ───────────────────────────────────────────────────────────────

    public function test_valid_semver_is_accepted(): void
    {
        foreach (['1.0.0', '1.2.3', '2.0.0'] as $version) {
            $manifest = ExtensionManifest::make($this->product(), $version, ExtensionSdkContract::V1, [$this->element()]);
            $this->assertSame($version, $manifest->packageVersion);
        }
    }

    public function test_invalid_semver_is_rejected(): void
    {
        foreach (['1.0', 'v1.0.0', '1.0.0-beta', 'not-a-version', ''] as $version) {
            try {
                ExtensionManifest::make($this->product(), $version, ExtensionSdkContract::V1, [$this->element()]);
                $this->fail("expected version \"{$version}\" to be rejected");
            } catch (ExtensionSdkException $e) {
                $this->assertStringContainsString('semver', $e->getMessage());
            }
        }
    }

    // ── SDK contract ─────────────────────────────────────────────────────────

    public function test_positive_supported_sdk_contract_is_accepted(): void
    {
        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$this->element()]);

        $this->assertSame(ExtensionSdkContract::V1, $manifest->sdkContractVersion);
    }

    public function test_invalid_sdk_contract_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', 0, [$this->element()]);
    }

    public function test_unsupported_sdk_contract_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', 999, [$this->element()]);
    }

    // ── elements ─────────────────────────────────────────────────────────────

    public function test_one_element_is_accepted(): void
    {
        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$this->element()]);

        $this->assertCount(1, $manifest->elements);
    }

    public function test_multiple_elements_are_accepted(): void
    {
        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [
            $this->element('ext:acme:gallery'),
            $this->element('ext:acme:carousel'),
        ]);

        $this->assertCount(2, $manifest->elements);
    }

    public function test_empty_elements_are_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, []);
    }

    public function test_duplicate_element_identity_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [
            $this->element('ext:acme:gallery'),
            $this->element('ext:acme:gallery'),
        ]);
    }

    public function test_foreign_vendor_block_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [
            $this->element('ext:notacme:gallery'),
        ]);
    }

    public function test_malformed_element_type_is_rejected(): void
    {
        foreach (['not-ext-prefixed', 'ext:acme', 'ext:Acme:gallery', 'ext:acme:Gallery', 'module:acme:gallery'] as $type) {
            try {
                ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$this->element($type)]);
                $this->fail("expected element type \"{$type}\" to be rejected");
            } catch (ExtensionSdkException $e) {
                $this->assertStringContainsString($type, $e->getMessage(), "expected the rejection for \"{$type}\" to name it");
            }
        }
    }

    // ── asset path namespace (3G-B-6B, ADR-048) ──────────────────────────────

    public function test_asset_path_namespaced_under_this_releases_own_product_and_version_is_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery-pro/1.0.0/gallery.css'],
        ]);

        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);

        $this->assertSame(['/vendor/acme/gallery-pro/1.0.0/gallery.css'], $manifest->elements['ext:acme:gallery']->assets['css']);
    }

    public function test_asset_path_missing_the_package_segment_is_rejected(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery.css'], // no /gallery-pro/1.0.0/ segment
        ]);

        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);
    }

    public function test_asset_path_under_a_different_products_package_segment_is_rejected(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/widgets-plus/1.0.0/gallery.css'], // different package
        ]);

        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);
    }

    public function test_asset_path_under_a_different_version_segment_is_rejected(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery-pro/2.0.0/gallery.css'], // this manifest is 1.0.0
        ]);

        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);
    }

    public function test_two_different_versions_of_the_same_product_get_distinct_required_prefixes(): void
    {
        $elementV1 = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery-pro/1.0.0/gallery.css'],
        ]);
        $manifestV1 = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$elementV1]);

        $elementV2 = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery-pro/2.0.0/gallery.css'],
        ]);
        $manifestV2 = ExtensionManifest::make($this->product(), '2.0.0', ExtensionSdkContract::V1, [$elementV2]);

        $this->assertNotSame(
            $manifestV1->elements['ext:acme:gallery']->assets['css'][0],
            $manifestV2->elements['ext:acme:gallery']->assets['css'][0]
        );
    }

    // ── controls: all 10 V1 types ────────────────────────────────────────────

    public function test_all_ten_v1_controls_are_accepted(): void
    {
        $controls = [
            ExtensionControlDefinition::make('a', ExtensionControlDefinition::TEXT),
            ExtensionControlDefinition::make('b', ExtensionControlDefinition::TEXTAREA),
            ExtensionControlDefinition::make('c', ExtensionControlDefinition::NUMBER),
            ExtensionControlDefinition::make('d', ExtensionControlDefinition::TOGGLE),
            ExtensionControlDefinition::make('e', ExtensionControlDefinition::SELECT, options: ['x', 'y']),
            ExtensionControlDefinition::make('f', ExtensionControlDefinition::COLOR),
            ExtensionControlDefinition::make('g', ExtensionControlDefinition::MEDIA),
            ExtensionControlDefinition::make('h', ExtensionControlDefinition::URL),
            ExtensionControlDefinition::make('i', ExtensionControlDefinition::SPACING),
            ExtensionControlDefinition::make('j', ExtensionControlDefinition::RESPONSIVE),
        ];

        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [
            $this->element('ext:acme:gallery', $controls),
        ]);

        $this->assertCount(10, $manifest->elements['ext:acme:gallery']->controls);
    }

    public function test_unsupported_control_type_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionControlDefinition::make('bad', 'repeater');
    }

    public function test_select_without_options_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionControlDefinition::make('choice', ExtensionControlDefinition::SELECT);
    }

    public function test_duplicate_control_key_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        $this->element('ext:acme:gallery', [
            ExtensionControlDefinition::make('title', ExtensionControlDefinition::TEXT),
            ExtensionControlDefinition::make('title', ExtensionControlDefinition::TEXTAREA),
        ]);
    }

    // ── container capability ─────────────────────────────────────────────────

    public function test_container_true_is_recorded(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:section', 'Section', 'layout', [], isContainer: true);
        $this->assertTrue($element->isContainer);
    }

    public function test_container_false_is_recorded(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], isContainer: false);
        $this->assertFalse($element->isContainer);
    }

    // ── allowed_children ─────────────────────────────────────────────────────

    public function test_allowed_children_valid_shape_is_accepted(): void
    {
        $element = ExtensionElementDefinition::make(
            'ext:acme:section',
            'Section',
            'layout',
            [],
            isContainer: true,
            allowedChildren: ['ext:acme:gallery', 'row'],
        );

        $this->assertSame(['ext:acme:gallery', 'row'], $element->allowedChildren);
    }

    public function test_allowed_children_invalid_shape_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:section', 'Section', 'layout', [], isContainer: true, allowedChildren: ['', 'row']);
    }

    // ── JSON-safe defaults ───────────────────────────────────────────────────

    public function test_json_safe_defaults_are_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [
            ExtensionControlDefinition::make('count', ExtensionControlDefinition::NUMBER, default: 3),
            ExtensionControlDefinition::make('items', ExtensionControlDefinition::TEXTAREA, default: ['a', 'b']),
        ]);

        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);

        $this->assertSame(['count' => 3, 'items' => ['a', 'b']], $manifest->elements['ext:acme:gallery']->defaults());
    }

    public function test_non_json_safe_default_is_rejected(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [
            ExtensionControlDefinition::make('handler', ExtensionControlDefinition::TEXT, default: new \stdClass()),
        ]);

        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);
    }

    // ── invalid object/list shape ────────────────────────────────────────────

    public function test_missing_elements_key_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::fromArray([
            'product_identity' => 'acme/gallery-pro',
            'package_version' => '1.0.0',
            'sdk_contract_version' => 1,
        ]);
    }

    public function test_list_shaped_element_entry_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionManifest::fromArray([
            'product_identity' => 'acme/gallery-pro',
            'package_version' => '1.0.0',
            'sdk_contract_version' => 1,
            'elements' => ['not-an-object'],
        ]);
    }

    // ── round-trip array serialization ───────────────────────────────────────

    public function test_round_trip_array_serialization_is_stable(): void
    {
        $element = ExtensionElementDefinition::make(
            type: 'ext:acme:gallery',
            label: 'Gallery',
            category: 'media',
            controls: [
                ExtensionControlDefinition::make('title', ExtensionControlDefinition::TEXT, label: 'Title', default: 'Untitled', required: true),
                ExtensionControlDefinition::make('layout', ExtensionControlDefinition::SELECT, default: 'grid', options: ['grid', 'list']),
            ],
            icon: 'image',
            isContainer: false,
            allowedChildren: null,
            hasAssets: true,
            assets: ['css' => ['/vendor/acme/gallery-pro/1.0.0/gallery.css'], 'js' => ['/vendor/acme/gallery-pro/1.0.0/gallery.js']],
        );

        $manifest = ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);

        $array = $manifest->toArray();
        $rebuilt = ExtensionManifest::fromArray($array);

        $this->assertSame($array, $rebuilt->toArray());
        $this->assertSame('acme/gallery-pro', $array['product_identity']);
        $this->assertSame('1.0.0', $array['package_version']);
        $this->assertSame(1, $array['sdk_contract_version']);
        $this->assertCount(1, $array['elements']);
        $this->assertSame(
            ['css' => ['/vendor/acme/gallery-pro/1.0.0/gallery.css'], 'js' => ['/vendor/acme/gallery-pro/1.0.0/gallery.js']],
            $array['elements'][0]['assets']
        );
    }
}
