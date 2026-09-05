<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-4 — the fail-closed outcome of asking "what Extension release
 * version did Theme authoring truthfully select for this product, and is
 * that selection still valid?" Three states only, never a guess:
 *
 *   - resolved:   a version was selected, an ExtensionRelease for exactly
 *                 that product+version genuinely exists, and its manifest
 *                 genuinely contains every element type this Theme actually
 *                 uses from that product. `versionConstraint` is the
 *                 truthful exact-version constraint string ("=X.Y.Z").
 *   - unresolved: no selection was recorded for this product at all.
 *   - invalid:    a selection was recorded but cannot be trusted — the
 *                 named release/version does not exist for this product, or
 *                 its manifest does not actually provide a used element
 *                 type. Never silently substituted with the current release
 *                 or a wildcard.
 */
final class ExtensionVersionSelectionResult
{
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_UNRESOLVED = 'unresolved';
    public const STATUS_INVALID = 'invalid';

    private function __construct(
        public readonly string $productIdentity,
        public readonly string $status,
        public readonly ?string $selectedVersion,
        public readonly ?string $versionConstraint,
        public readonly ?string $invalidReason,
    ) {
    }

    public static function resolved(string $productIdentity, string $selectedVersion, string $versionConstraint): self
    {
        return new self($productIdentity, self::STATUS_RESOLVED, $selectedVersion, $versionConstraint, null);
    }

    public static function unresolved(string $productIdentity): self
    {
        return new self($productIdentity, self::STATUS_UNRESOLVED, null, null, null);
    }

    public static function invalid(string $productIdentity, string $selectedVersion, string $reason): self
    {
        return new self($productIdentity, self::STATUS_INVALID, $selectedVersion, null, $reason);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isUnresolved(): bool
    {
        return $this->status === self::STATUS_UNRESOLVED;
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }
}
