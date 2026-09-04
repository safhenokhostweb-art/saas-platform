<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-3 — the pure, immutable outcome of running
 * ThemeExtensionDependencyExtractor over a Theme snapshot. Deliberately not
 * reduced to a boolean — a later publish-gate UI needs to explain exactly
 * which element types were found, who owns each, and which are unresolved
 * or ambiguous (see ADR-045/3G-B-3 traceability requirement). Never
 * persists anything itself.
 */
final class ThemeExtensionDependencyExtractionResult
{
    /**
     * @param ExtensionDependencyProposal[] $resolvedDependencies deduplicated by product identity, ordered lexically
     * @param string[] $unresolvedElementTypes ext:* types with zero owning Extension product, sorted
     * @param array<string, string[]> $ambiguousElementTypes ext:* type => 2+ candidate product identities, sorted
     * @param array<string, ?string> $ownershipMap every scanned ext:* type => resolved product identity, or null if unresolved/ambiguous
     */
    public function __construct(
        public readonly array $resolvedDependencies,
        public readonly array $unresolvedElementTypes,
        public readonly array $ambiguousElementTypes,
        public readonly array $ownershipMap,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [], []);
    }

    public function hasUnresolved(): bool
    {
        return $this->unresolvedElementTypes !== [];
    }

    public function hasAmbiguous(): bool
    {
        return $this->ambiguousElementTypes !== [];
    }

    /** True only when every discovered ext:* type resolved to exactly one owning product. */
    public function isFullyResolved(): bool
    {
        return !$this->hasUnresolved() && !$this->hasAmbiguous();
    }
}
