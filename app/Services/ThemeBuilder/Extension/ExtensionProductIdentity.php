<?php

namespace App\Services\ThemeBuilder\Extension;

/**
 * Phase 3G-B-1 — the saas-platform-side copy of the Extension PRODUCT
 * identity grammar: "{vendor-slug}/{package-slug}".
 *
 * DELIBERATE, TEMPORARY DUPLICATION (ADR-045 point 5): this grammar is
 * reproduced byte-identically from customer-site's already-proven
 * App\Services\ThemeCanvas\Extension\ExtensionBlockTypeIdentity::
 * isValidVendorSlug() (vendor half) and
 * App\Services\ThemeCanvas\Extension\ExtensionProductIdentity (package
 * half) — never imported (saas-platform must never depend on customer-site
 * App\*), never a divergent second grammar. Whoever changes either copy
 * must update the other to keep them in parity until the shared
 * extension-sdk package (3G-B-2) makes this file's logic obsolete.
 *
 * Deliberately NOT Unicode-aware, matching the customer-site original:
 * these are developer-facing package identifiers, not user-facing content.
 */
final class ExtensionProductIdentity
{
    private const VENDOR_SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
    private const PACKAGE_SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const VENDOR_SLUG_MIN = 2;
    private const VENDOR_SLUG_MAX = 40;
    private const PACKAGE_SLUG_MIN = 2;
    private const PACKAGE_SLUG_MAX = 60;

    public static function isValidVendorSlug(string $vendorSlug): bool
    {
        $length = strlen($vendorSlug);

        return $length >= self::VENDOR_SLUG_MIN
            && $length <= self::VENDOR_SLUG_MAX
            && preg_match(self::VENDOR_SLUG_PATTERN, $vendorSlug) === 1;
    }

    public static function isValidPackageSlug(string $packageSlug): bool
    {
        $length = strlen($packageSlug);

        return $length >= self::PACKAGE_SLUG_MIN
            && $length <= self::PACKAGE_SLUG_MAX
            && preg_match(self::PACKAGE_SLUG_PATTERN, $packageSlug) === 1;
    }

    public static function isValidIdentifier(string $identifier): bool
    {
        $parts = explode('/', $identifier);

        return count($parts) === 2
            && self::isValidVendorSlug($parts[0])
            && self::isValidPackageSlug($parts[1]);
    }
}
