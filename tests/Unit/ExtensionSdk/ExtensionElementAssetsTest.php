<?php

namespace Tests\Unit\ExtensionSdk;

use SaaSPlatform\ExtensionSdk\ExtensionElementDefinition;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use SaaSPlatform\ExtensionSdk\ExtensionSdkContract;
use SaaSPlatform\ExtensionSdk\ExtensionSdkException;
use Tests\TestCase;

/**
 * Phase 3G-B-6A (ADR-048) — the declarative Extension asset contract:
 * `ExtensionElementDefinition::$assets` = `{css: string[], js: string[]}`,
 * each entry a root-relative public path (exactly one leading "/", no ".."
 * segments, no scheme, ASCII letters/digits/"_-./" only, matching
 * extension) — the exact value shape customer-site's own
 * `HasAssets::getAssets()` already returns and its Blade views already
 * emit verbatim as `<link href>`/`<script src>`. Never a full URL, never a
 * filesystem path, never an executable reference of any kind.
 */
class ExtensionElementAssetsTest extends TestCase
{
    private function product(): ExtensionProductIdentity
    {
        return ExtensionProductIdentity::fromParts('acme', 'gallery-pro');
    }

    private function manifestWith(ExtensionElementDefinition $element): ExtensionManifest
    {
        return ExtensionManifest::make($this->product(), '1.0.0', ExtensionSdkContract::V1, [$element]);
    }

    // ── backward compatibility (§5) ──────────────────────────────────────────

    public function test_manifest_without_assets_key_remains_valid(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'product_identity' => 'acme/gallery-pro',
            'package_version' => '1.0.0',
            'sdk_contract_version' => 1,
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'media']],
        ]);

        $this->assertSame(['css' => [], 'js' => []], $manifest->elements['ext:acme:gallery']->assets);
        $this->assertFalse($manifest->elements['ext:acme:gallery']->hasAssets);
    }

    public function test_element_make_with_no_assets_argument_defaults_to_empty(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', []);

        $this->assertSame(['css' => [], 'js' => []], $element->assets);
        $this->assertFalse($element->hasAssets);
    }

    // ── shapes: css-only, js-only, css+js ────────────────────────────────────

    public function test_css_only_assets_are_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery.css'],
        ]);

        $this->assertSame(['/vendor/acme/gallery.css'], $element->assets['css']);
        $this->assertSame([], $element->assets['js']);
    }

    public function test_js_only_assets_are_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'js' => ['/vendor/acme/gallery.js'],
        ]);

        $this->assertSame([], $element->assets['css']);
        $this->assertSame(['/vendor/acme/gallery.js'], $element->assets['js']);
    }

    public function test_css_and_js_assets_are_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery.css'],
            'js' => ['/vendor/acme/gallery.js'],
        ]);

        $this->assertSame(['/vendor/acme/gallery.css'], $element->assets['css']);
        $this->assertSame(['/vendor/acme/gallery.js'], $element->assets['js']);
    }

    public function test_multiple_entries_per_family_are_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/a.css', '/vendor/acme/b.css'],
        ]);

        $this->assertCount(2, $element->assets['css']);
    }

    // ── deterministic round trip ─────────────────────────────────────────────

    public function test_assets_round_trip_through_from_array_deterministically(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery-pro/1.0.0/a.css', '/vendor/acme/gallery-pro/1.0.0/b.css'],
            'js' => ['/vendor/acme/gallery-pro/1.0.0/a.js'],
        ]);
        $manifest = $this->manifestWith($element);

        $array = $manifest->toArray();
        $rebuilt = ExtensionManifest::fromArray($array);

        $this->assertSame($array, $rebuilt->toArray());
        $this->assertSame($array['elements'][0]['assets'], $rebuilt->elements['ext:acme:gallery']->assets);
    }

    public function test_new_manifest_always_carries_an_explicit_empty_assets_key(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', []);
        $array = $element->toArray();

        $this->assertArrayHasKey('assets', $array);
        $this->assertSame(['css' => [], 'js' => []], $array['assets']);
    }

    // ── has_assets consistency (§4) ──────────────────────────────────────────

    public function test_has_assets_true_with_empty_assets_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: []);
    }

    public function test_has_assets_false_with_non_empty_assets_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: false, assets: [
            'css' => ['/vendor/acme/gallery.css'],
        ]);
    }

    public function test_has_assets_true_with_only_js_is_consistent(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'js' => ['/vendor/acme/gallery.js'],
        ]);

        $this->assertTrue($element->hasAssets);
    }

    // ── shape validation ──────────────────────────────────────────────────────

    public function test_unknown_asset_family_key_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], assets: [
            'css' => [],
            'php' => ['SomeClass::method'],
        ]);
    }

    public function test_non_list_asset_family_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], assets: [
            'css' => ['keyed' => '/vendor/acme/gallery.css'],
        ]);
    }

    public function test_non_string_asset_entry_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], assets: [
            'css' => [123],
        ]);
    }

    public function test_wrong_extension_for_family_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], assets: [
            'css' => ['/vendor/acme/gallery.js'], // .js in the css family
        ]);
    }

    // ── security: malicious/invalid reference values ─────────────────────────

    /** @dataProvider maliciousCssReferences */
    public function test_malicious_or_invalid_css_reference_is_rejected(string $value): void
    {
        try {
            ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], assets: ['css' => [$value]]);
            $this->fail("expected asset reference \"{$value}\" to be rejected");
        } catch (ExtensionSdkException $e) {
            $this->assertStringContainsString('asset reference', $e->getMessage());
        }
    }

    public static function maliciousCssReferences(): array
    {
        return [
            'path traversal' => ['/../../etc/passwd.css'],
            'traversal after valid prefix' => ['/vendor/acme/../../etc/passwd.css'],
            'javascript scheme' => ['javascript:alert(1).css'],
            'data uri' => ['data:text/html,<script>alert(1)</script>.css'],
            'quote breakout' => ['/vendor/acme/"><script>alert(1)</script>.css'],
            'angle bracket markup' => ['/vendor/acme/<b>x</b>.css'],
            'protocol relative' => ['//evil.example.com/x.css'],
            'absolute https url' => ['https://evil.example.com/x.css'],
            'absolute http url' => ['http://evil.example.com/x.css'],
            'no leading slash' => ['vendor/acme/gallery.css'],
            'backslash windows path' => ['\\vendor\\acme\\gallery.css'],
            'absolute filesystem path with colon' => ['C:/Windows/x.css'],
            'space in value' => ['/vendor/acme/my file.css'],
            'empty string' => [''],
            'wrong extension' => ['/vendor/acme/gallery.php'],
            'null byte style traversal' => ['/vendor/acme/..%2f..%2fetc%2fpasswd.css'],
        ];
    }

    public function test_relative_dot_segment_is_rejected(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], assets: [
            'css' => ['/vendor/./acme/gallery.css'],
        ]);
    }

    public function test_valid_nested_path_is_accepted(): void
    {
        $element = ExtensionElementDefinition::make('ext:acme:gallery', 'Gallery', 'media', [], hasAssets: true, assets: [
            'css' => ['/vendor/acme/gallery-pro/v1/gallery.css'],
        ]);

        $this->assertSame(['/vendor/acme/gallery-pro/v1/gallery.css'], $element->assets['css']);
    }
}
