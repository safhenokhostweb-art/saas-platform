<?php

namespace SaaSPlatform\ExtensionSdk;

use RuntimeException;

/**
 * Phase 3G-B-2 — the single failure type for this package's declarative
 * contract validation (product identity, manifest, element, control). Every
 * case fails closed: no partial/invalid DTO is ever returned when this is
 * thrown, and no Laravel/framework exception hierarchy is depended upon
 * (this package must remain usable without booting Laravel).
 */
final class ExtensionSdkException extends RuntimeException
{
    public static function malformedVendorSlug(string $vendorSlug): self
    {
        return new self(
            "Invalid extension vendor slug \"{$vendorSlug}\" — must be lowercase ASCII "
            . 'letters, digits, and hyphens only, 2-40 characters, no leading/trailing/'
            . 'double hyphen.'
        );
    }

    public static function malformedPackageSlug(string $packageSlug): self
    {
        return new self(
            "Invalid extension package slug \"{$packageSlug}\" — must be lowercase ASCII "
            . 'letters, digits, and hyphens only, 2-60 characters, no leading/trailing/'
            . 'double hyphen.'
        );
    }

    public static function malformedProductIdentifier(string $identifier): self
    {
        return new self(
            "Invalid extension product identifier \"{$identifier}\" — must be exactly "
            . '"{vendor-slug}/{package-slug}", each a valid slug on its own.'
        );
    }

    public static function malformedVersion(string $version): self
    {
        return new self("Package version \"{$version}\" must be semver (e.g. 1.0.0).");
    }

    public static function unsupportedSdkContractVersion(int $requested, array $supported): self
    {
        $list = implode(', ', $supported);

        return new self("Unsupported SDK contract version {$requested} — this manifest authority supports: {$list}.");
    }

    public static function emptyElements(): self
    {
        return new self('A manifest must declare at least one element.');
    }

    public static function malformedElementType(string $type): self
    {
        return new self(
            "Invalid element type \"{$type}\" — must be \"ext:{vendor-slug}:{block_name}\" with a "
            . 'valid vendor slug and a lowercase, underscore-separated block name (1-60 characters).'
        );
    }

    public static function duplicateElementIdentity(string $type): self
    {
        return new self("Element type \"{$type}\" is declared more than once within the same manifest.");
    }

    public static function elementVendorMismatch(string $type, string $productVendor): self
    {
        return new self(
            "Element \"{$type}\" does not belong to vendor \"{$productVendor}\" — every element in a "
            . 'manifest must be namespaced under its own product\'s vendor.'
        );
    }

    public static function unknownControlType(string $key, string $controlType): self
    {
        return new self(
            "Control \"{$key}\" declares unknown control type \"{$controlType}\". "
            . 'Only the V1 control vocabulary is supported.'
        );
    }

    public static function duplicateControlKey(string $key): self
    {
        return new self("Duplicate control key \"{$key}\" — every control key must be unique within one element.");
    }

    public static function invalidSelectOptions(string $key): self
    {
        return new self("Control \"{$key}\" is a select control but declares no non-empty \"options\" list.");
    }

    public static function invalidAllowedChildrenShape(string $elementType): self
    {
        return new self("Element \"{$elementType}\"'s \"allowed_children\" must be null or a list of non-empty type strings.");
    }

    public static function nonJsonSafeValue(string $context): self
    {
        return new self("Element \"{$context}\" declares a control default that is not JSON-safe (scalars, arrays, and null only).");
    }

    public static function invalidManifestShape(string $reason): self
    {
        return new self("Invalid manifest: {$reason}");
    }

    public static function invalidAssetShape(string $elementType): self
    {
        return new self(
            "Element \"{$elementType}\"'s \"assets\" must be an object with only \"css\" and \"js\" "
            . 'keys, each a list of strings.'
        );
    }

    public static function invalidAssetReference(string $elementType, string $family, string $value): self
    {
        return new self(
            "Element \"{$elementType}\" declares an invalid {$family} asset reference \"{$value}\" — "
            . 'must be a root-relative public path (starting with exactly one "/", no ".." segments, '
            . 'no scheme, no query/fragment, ASCII letters/digits/"_-./" only) ending in the correct extension.'
        );
    }

    public static function hasAssetsInconsistentWithAssets(string $elementType): self
    {
        return new self(
            "Element \"{$elementType}\"'s \"has_assets\" flag is inconsistent with its \"assets\" — "
            . 'has_assets=true requires at least one css/js entry, has_assets=false requires both to be empty.'
        );
    }

    public static function assetPathNotNamespacedUnderRelease(string $elementType, string $path, string $requiredPrefix): self
    {
        return new self(
            "Element \"{$elementType}\" declares asset \"{$path}\" which is not namespaced under "
            . "\"{$requiredPrefix}\" — every asset path must live under this exact release's own "
            . 'product/version prefix (ADR-048), so no two releases can ever declare the same physical path.'
        );
    }
}
