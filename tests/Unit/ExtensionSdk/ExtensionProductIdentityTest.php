<?php

namespace Tests\Unit\ExtensionSdk;

use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use SaaSPlatform\ExtensionSdk\ExtensionSdkException;
use Tests\TestCase;

/**
 * Phase 3G-B-2 (ADR-045 point 5) — the canonical Extension PRODUCT identity
 * grammar test, superseding the prior "parity" test
 * (tests/Unit/ThemeBuilder/Extension/ExtensionProductIdentityTest.php, now
 * deleted) that proved saas-platform's local duplicate copy matched
 * customer-site's. There is now only ONE grammar authority — this package —
 * so there is nothing left to prove "in parity"; this test exercises it
 * directly. Same accept/reject vectors as both prior copies used, so no
 * behavior silently weakened during the collapse.
 */
class ExtensionProductIdentityTest extends TestCase
{
    // ── valid vendor slugs ──────────────────────────────────────────────────

    public function test_valid_vendor_slugs_are_accepted(): void
    {
        foreach (['acme', 'foo', 'a1', 'multi-word-vendor', 'ab'] as $vendor) {
            $this->assertTrue(ExtensionProductIdentity::isValidVendorSlug($vendor), "expected \"{$vendor}\" to be valid");
        }
    }

    public function test_vendor_slug_length_bounds_2_to_40(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidVendorSlug('a'));
        $this->assertTrue(ExtensionProductIdentity::isValidVendorSlug(str_repeat('a', 40)));
        $this->assertFalse(ExtensionProductIdentity::isValidVendorSlug(str_repeat('a', 41)));
    }

    public function test_invalid_vendor_slugs_are_rejected(): void
    {
        $invalid = [
            '', 'Acme', 'acme_vendor', ' acme', 'acme ', 'acme/vendor',
            '-acme', 'acme-', 'acme--vendor', '../acme', 'acme.vendor',
        ];

        foreach ($invalid as $vendor) {
            $this->assertFalse(ExtensionProductIdentity::isValidVendorSlug($vendor), "expected \"{$vendor}\" to be invalid");
        }
    }

    // ── valid/invalid package slugs ─────────────────────────────────────────

    public function test_valid_package_slugs_are_accepted(): void
    {
        foreach (['gallery-pro', 'maps', 'a1', 'multi-word-package'] as $package) {
            $this->assertTrue(ExtensionProductIdentity::isValidPackageSlug($package), "expected \"{$package}\" to be valid");
        }
    }

    public function test_package_slug_length_bounds_2_to_60(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidPackageSlug('a'));
        $this->assertTrue(ExtensionProductIdentity::isValidPackageSlug(str_repeat('a', 60)));
        $this->assertFalse(ExtensionProductIdentity::isValidPackageSlug(str_repeat('a', 61)));
    }

    public function test_invalid_package_slugs_are_rejected(): void
    {
        $invalid = ['', 'Gallery-Pro', 'gallery_pro', ' gallery', 'gallery ', '-gallery', 'gallery-', '../gallery'];

        foreach ($invalid as $package) {
            $this->assertFalse(ExtensionProductIdentity::isValidPackageSlug($package), "expected \"{$package}\" to be invalid");
        }
    }

    // ── full "{vendor}/{package}" identifier ────────────────────────────────

    public function test_valid_full_identifiers_are_accepted(): void
    {
        foreach (['acme/gallery-pro', 'foo/maps'] as $identifier) {
            $this->assertTrue(ExtensionProductIdentity::isValidIdentifier($identifier), "expected \"{$identifier}\" to be valid");
        }
    }

    public function test_a_bare_vendor_slug_is_not_a_valid_product_identifier(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('acme'));
    }

    public function test_a_bare_block_name_with_no_vendor_is_rejected(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('gallery-pro'));
    }

    public function test_missing_vendor_half_is_rejected(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('/gallery-pro'));
    }

    public function test_missing_package_half_is_rejected(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('acme/'));
    }

    public function test_extra_slash_is_rejected(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('acme/gallery/pro'));
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('../acme/gallery-pro'));
        $this->assertFalse(ExtensionProductIdentity::isValidIdentifier('acme/../gallery-pro'));
    }

    // ── instance construction / value semantics (new surface vs. the old
    //    static-only saas-platform copy — proves the shared authority's
    //    fuller API used by ExtensionManifest) ──────────────────────────────

    public function test_from_parts_and_from_string_round_trip(): void
    {
        $viaParts = ExtensionProductIdentity::fromParts('acme', 'gallery-pro');
        $viaString = ExtensionProductIdentity::fromString('acme/gallery-pro');

        $this->assertSame('acme', $viaParts->vendorSlug());
        $this->assertSame('gallery-pro', $viaParts->packageSlug());
        $this->assertSame('acme/gallery-pro', $viaParts->toString());
        $this->assertSame((string) $viaParts, $viaParts->toString());
        $this->assertTrue($viaParts->equals($viaString));
    }

    public function test_from_parts_throws_on_malformed_vendor(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionProductIdentity::fromParts('Acme', 'gallery-pro');
    }

    public function test_from_parts_throws_on_malformed_package(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionProductIdentity::fromParts('acme', 'Gallery_Pro');
    }

    public function test_from_string_throws_on_extra_slash(): void
    {
        $this->expectException(ExtensionSdkException::class);
        ExtensionProductIdentity::fromString('acme/gallery/pro');
    }

    public function test_two_different_products_are_not_equal(): void
    {
        $one = ExtensionProductIdentity::fromParts('acme', 'gallery-pro');
        $two = ExtensionProductIdentity::fromParts('acme', 'forms-pro');

        $this->assertFalse($one->equals($two));
    }
}
