<?php

namespace SaaSPlatform\ExtensionSdk;

/**
 * Phase 3G-B-2 (ADR-045 point 5) — the ONE canonical Extension PRODUCT
 * identity authority: "{vendor-slug}/{package-slug}". Collapses the prior
 * deliberate byte-identical duplication between saas-platform's
 * App\Services\ThemeBuilder\Extension\ExtensionProductIdentity and
 * customer-site's App\Services\ThemeCanvas\Extension\ExtensionProductIdentity
 * into this single shared package, importable by both repos without either
 * depending on the other's App\* namespace.
 *
 * Deliberately distinct from a persisted BLOCK identity
 * (`ext:{vendor-slug}:{block-name}`, owned solely by customer-site's
 * ExtensionBlockTypeIdentity/ExtensionBlockTypeRegistry — never merged
 * here): one product may provide many block types, and a package/version
 * concern is never the same concern as a registry-collision concern.
 *
 * Deliberately NOT Unicode-aware, matching both prior originals: these are
 * developer-facing package identifiers, not user-facing content.
 */
final class ExtensionProductIdentity
{
    private const VENDOR_SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
    private const PACKAGE_SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const VENDOR_SLUG_MIN = 2;
    private const VENDOR_SLUG_MAX = 40;
    private const PACKAGE_SLUG_MIN = 2;
    private const PACKAGE_SLUG_MAX = 60;

    private function __construct(
        private readonly string $vendorSlug,
        private readonly string $packageSlug,
    ) {
    }

    public static function fromParts(string $vendorSlug, string $packageSlug): self
    {
        if (!self::isValidVendorSlug($vendorSlug)) {
            throw ExtensionSdkException::malformedVendorSlug($vendorSlug);
        }

        if (!self::isValidPackageSlug($packageSlug)) {
            throw ExtensionSdkException::malformedPackageSlug($packageSlug);
        }

        return new self($vendorSlug, $packageSlug);
    }

    public static function fromString(string $identifier): self
    {
        $parts = explode('/', $identifier);

        if (count($parts) !== 2) {
            throw ExtensionSdkException::malformedProductIdentifier($identifier);
        }

        return self::fromParts($parts[0], $parts[1]);
    }

    /** Non-throwing check for fail-closed callers (e.g. a dependency resolver's resolve()). */
    public static function isValidIdentifier(string $identifier): bool
    {
        $parts = explode('/', $identifier);

        return count($parts) === 2
            && self::isValidVendorSlug($parts[0])
            && self::isValidPackageSlug($parts[1]);
    }

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

    public function vendorSlug(): string
    {
        return $this->vendorSlug;
    }

    public function packageSlug(): string
    {
        return $this->packageSlug;
    }

    public function toString(): string
    {
        return "{$this->vendorSlug}/{$this->packageSlug}";
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
