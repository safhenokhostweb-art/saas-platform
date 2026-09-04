<?php

namespace Tests\Unit\ThemeBuilder\Extension;

use App\Services\ThemeBuilder\Extension\ExtensionProductIdentity;
use Tests\TestCase;

/**
 * Phase 3G-B-1 (ADR-045) — proves this saas-platform-side grammar copy is in
 * exact parity with customer-site's already-proven
 * App\Services\ThemeCanvas\Extension\ExtensionBlockTypeIdentity::
 * isValidVendorSlug() (vendor half, 2-40 chars, `/^[a-z0-9]+(-[a-z0-9]+)*$/`)
 * and App\Services\ThemeCanvas\Extension\ExtensionProductIdentity (package
 * half, 2-60 chars, same pattern) — same accept/reject vectors, never a
 * divergent second grammar. Does NOT import the customer-site classes
 * (saas-platform must never depend on customer-site App\*) — parity is
 * proven by running the same literal test vectors against this copy.
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
        $this->assertFalse(ExtensionProductIdentity::isValidVendorSlug('a')); // 1 char, below min
        $this->assertTrue(ExtensionProductIdentity::isValidVendorSlug(str_repeat('a', 40))); // exactly max
        $this->assertFalse(ExtensionProductIdentity::isValidVendorSlug(str_repeat('a', 41))); // over max
    }

    public function test_invalid_vendor_slugs_are_rejected(): void
    {
        $invalid = [
            '' ,                 // empty
            'Acme',               // uppercase
            'acme_vendor',        // underscore not allowed in vendor grammar
            ' acme',              // leading whitespace
            'acme ',              // trailing whitespace
            'acme/vendor',        // embedded slash
            '-acme',              // leading hyphen
            'acme-',              // trailing hyphen
            'acme--vendor',       // double hyphen (pattern requires single [a-z0-9]+ groups)
            '../acme',            // path traversal
            'acme.vendor',        // punctuation not allowed
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
        // A vendor may publish several independent products — a bare vendor
        // slug alone is deliberately too coarse (ADR-037/ADR-045).
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
}
