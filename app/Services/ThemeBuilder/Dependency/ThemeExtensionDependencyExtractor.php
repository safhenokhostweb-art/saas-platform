<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-3 — combines ThemeExtensionBlockTypeExtractor (snapshot →
 * unique ext:* types) with ExtensionElementOwnershipResolver (type → owning
 * Extension product) into the final, deterministic, deduplicated Theme→
 * Extension dependency proposal set.
 *
 * Phase 3G-B-4 — additionally consults ExtensionVersionSelectionResolver
 * against Theme authoring's own recorded Extension version selections
 * (`settings.extension_selections`, read from whatever settings array the
 * caller passes — the live Theme draft or an already-frozen
 * ThemeRelease.snapshot.settings) so a resulting proposal's
 * `versionConstraint` is always a truthful "=X.Y.Z" derived from what
 * authoring actually selected — never null-by-default, never guessed. A
 * product with resolved ownership but no truthful version selection is
 * excluded from `resolvedDependencies` entirely (see
 * ThemeExtensionDependencyExtractionResult's doc comment) rather than
 * included with an invented constraint.
 *
 * Deduplication rule: multiple element types owned by the same Extension
 * product collapse into ONE ExtensionDependencyProposal for that product
 * (never one dependency per block/type occurrence).
 *
 * Ordering rule: resolved dependencies are ordered lexically by product
 * identity; each proposal's own element-type list is sorted lexically too —
 * both deterministic, no existing Theme dependency ordering convention to
 * defer to (ThemeReleaseDependency rows carry no explicit sort column).
 *
 * NOT wired into ThemeReleaseService::release()/attachDependencies() or the
 * Commercial Publish Gate in 3G-B-3/3G-B-4 — that integration is a later
 * phase's decision, now that a truthful version-constraint source exists
 * (this class) but has not yet been connected to publication.
 */
final class ThemeExtensionDependencyExtractor
{
    private const SETTINGS_SELECTIONS_KEY = 'extension_selections';

    public function __construct(
        private readonly ThemeExtensionBlockTypeExtractor $blockTypeExtractor,
        private readonly ExtensionElementOwnershipResolver $ownershipResolver,
        private readonly ExtensionVersionSelectionResolver $versionSelectionResolver,
    ) {
    }

    /**
     * @param array<string, array{version?: string}|string> $extensionSelections product identity => selected version (or `['version' => ...]`)
     */
    public function extractFromSnapshot(array $headerBlocks, array $footerBlocks, array $pages, array $extensionSelections = []): ThemeExtensionDependencyExtractionResult
    {
        $elementTypes = $this->blockTypeExtractor->extractFromSnapshot($headerBlocks, $footerBlocks, $pages);
        $selections = $this->normalizeSelections($extensionSelections);

        if ($elementTypes === [] && $selections === []) {
            return ThemeExtensionDependencyExtractionResult::empty();
        }

        return $this->buildResult($this->ownershipResolver->resolve($elementTypes), $selections);
    }

    /**
     * Convenience over a full settings array (Theme.settings, or a frozen
     * ThemeRelease.snapshot.settings) — reads `extension_selections` from
     * it. Not wired into any authoring/read path yet; the settings key
     * itself is not written anywhere until a future authoring UI (B-6)
     * exists — this method only defines the contract that UI will feed.
     */
    public function extractFromReleaseSnapshot(array $snapshot): ThemeExtensionDependencyExtractionResult
    {
        return $this->extractFromSnapshot(
            $snapshot['header_blocks'] ?? [],
            $snapshot['footer_blocks'] ?? [],
            $snapshot['pages'] ?? [],
            $snapshot['settings'][self::SETTINGS_SELECTIONS_KEY] ?? [],
        );
    }

    /** @param array<string, array{version?: string}|string> $raw */
    private function normalizeSelections(array $raw): array
    {
        $selections = [];
        foreach ($raw as $productIdentity => $value) {
            if (is_string($value)) {
                $selections[$productIdentity] = $value;
            } elseif (is_array($value) && isset($value['version']) && is_string($value['version'])) {
                $selections[$productIdentity] = $value['version'];
            }
        }

        return $selections;
    }

    /**
     * @param array<string, ElementOwnershipResult> $ownership
     * @param array<string, string> $selections
     */
    private function buildResult(array $ownership, array $selections): ThemeExtensionDependencyExtractionResult
    {
        /** @var array<string, string[]> $productToTypes */
        $productToTypes = [];
        $unresolved = [];
        $ambiguous = [];
        $ownershipMap = [];

        foreach ($ownership as $type => $result) {
            if ($result->isResolved()) {
                $productToTypes[$result->productIdentity][] = $type;
                $ownershipMap[$type] = $result->productIdentity;
            } elseif ($result->isAmbiguous()) {
                $ambiguous[$type] = $result->candidateProductIdentities;
                $ownershipMap[$type] = null;
            } else {
                $unresolved[] = $type;
                $ownershipMap[$type] = null;
            }
        }

        ksort($productToTypes, SORT_STRING);
        foreach ($productToTypes as &$types) {
            sort($types, SORT_STRING);
        }
        unset($types);

        $versionResults = $this->versionSelectionResolver->resolve($selections, $productToTypes);

        $dependencies = [];
        $unresolvedVersionSelections = [];
        $invalidVersionSelections = [];

        foreach ($productToTypes as $productIdentity => $types) {
            $versionResult = $versionResults[$productIdentity];

            if ($versionResult->isResolved()) {
                $dependencies[] = new ExtensionDependencyProposal($productIdentity, $types, true, $versionResult->versionConstraint);
            } elseif ($versionResult->isInvalid()) {
                $invalidVersionSelections[$productIdentity] = $versionResult->invalidReason;
            } else {
                $unresolvedVersionSelections[] = $productIdentity;
            }
        }

        $usedProducts = array_keys($productToTypes);
        $unusedSelections = array_values(array_diff(array_keys($selections), $usedProducts));

        sort($unresolved, SORT_STRING);
        ksort($ambiguous, SORT_STRING);
        sort($unresolvedVersionSelections, SORT_STRING);
        ksort($invalidVersionSelections, SORT_STRING);
        sort($unusedSelections, SORT_STRING);

        return new ThemeExtensionDependencyExtractionResult(
            $dependencies,
            $unresolved,
            $ambiguous,
            $ownershipMap,
            $unresolvedVersionSelections,
            $invalidVersionSelections,
            $unusedSelections,
        );
    }
}
