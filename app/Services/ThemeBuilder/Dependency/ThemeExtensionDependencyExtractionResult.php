<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-3 — the pure, immutable outcome of running
 * ThemeExtensionDependencyExtractor over a Theme snapshot. Deliberately not
 * reduced to a boolean — a later publish-gate UI needs to explain exactly
 * which element types were found, who owns each, and which are unresolved
 * or ambiguous (see ADR-045/3G-B-3 traceability requirement). Never
 * persists anything itself.
 *
 * Phase 3G-B-4 — a product only ever lands in `resolvedDependencies` when
 * BOTH its element ownership AND its authoring-selected Extension release
 * version are truthfully resolved. A product with resolved ownership but no
 * (or an invalid) version selection is deliberately held OUT of
 * `resolvedDependencies` — never included with a null/guessed constraint —
 * and reported instead via `unresolvedVersionSelections`/
 * `invalidVersionSelections`, so a consumer can never mistake "no truthful
 * version yet" for "no constraint needed."
 */
final class ThemeExtensionDependencyExtractionResult
{
    /**
     * @param ExtensionDependencyProposal[] $resolvedDependencies deduplicated by product identity, ordered lexically — version-selection-resolved only
     * @param string[] $unresolvedElementTypes ext:* types with zero owning Extension product, sorted
     * @param array<string, string[]> $ambiguousElementTypes ext:* type => 2+ candidate product identities, sorted
     * @param array<string, ?string> $ownershipMap every scanned ext:* type => resolved product identity, or null if unresolved/ambiguous
     * @param string[] $unresolvedVersionSelections product identities with resolved ownership but no recorded version selection, sorted
     * @param array<string, string> $invalidVersionSelections product identity => reason, for a recorded selection that cannot be trusted, sorted by key
     * @param string[] $unusedSelections product identities with a recorded version selection that no used element type currently attributes to them, sorted
     */
    public function __construct(
        public readonly array $resolvedDependencies,
        public readonly array $unresolvedElementTypes,
        public readonly array $ambiguousElementTypes,
        public readonly array $ownershipMap,
        public readonly array $unresolvedVersionSelections,
        public readonly array $invalidVersionSelections,
        public readonly array $unusedSelections,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [], [], [], [], []);
    }

    public function hasUnresolved(): bool
    {
        return $this->unresolvedElementTypes !== [];
    }

    public function hasAmbiguous(): bool
    {
        return $this->ambiguousElementTypes !== [];
    }

    public function hasUnresolvedVersionSelections(): bool
    {
        return $this->unresolvedVersionSelections !== [];
    }

    public function hasInvalidVersionSelections(): bool
    {
        return $this->invalidVersionSelections !== [];
    }

    /** True only when every discovered ext:* type resolved to exactly one owning product with a valid element set. */
    public function isFullyResolved(): bool
    {
        return !$this->hasUnresolved() && !$this->hasAmbiguous();
    }

    /** True only when every fully-owned product also has a truthful, valid version selection — the bar a publish gate would need. */
    public function isFullyResolvedWithVersions(): bool
    {
        return $this->isFullyResolved() && !$this->hasUnresolvedVersionSelections() && !$this->hasInvalidVersionSelections();
    }
}
