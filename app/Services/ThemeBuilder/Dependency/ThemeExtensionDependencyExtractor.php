<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-3 — combines ThemeExtensionBlockTypeExtractor (snapshot →
 * unique ext:* types) with ExtensionElementOwnershipResolver (type → owning
 * Extension product) into the final, deterministic, deduplicated Theme→
 * Extension dependency proposal set.
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
 * NOT wired into ThemeReleaseService::release()/attachDependencies() in
 * this phase — that integration is a later phase's decision once a truthful
 * version-constraint source and the commercial publish gate are ready (see
 * ADR-045/3G-B-3 boundary notes).
 */
final class ThemeExtensionDependencyExtractor
{
    public function __construct(
        private readonly ThemeExtensionBlockTypeExtractor $blockTypeExtractor,
        private readonly ExtensionElementOwnershipResolver $ownershipResolver,
    ) {
    }

    public function extractFromSnapshot(array $headerBlocks, array $footerBlocks, array $pages): ThemeExtensionDependencyExtractionResult
    {
        $elementTypes = $this->blockTypeExtractor->extractFromSnapshot($headerBlocks, $footerBlocks, $pages);

        if ($elementTypes === []) {
            return ThemeExtensionDependencyExtractionResult::empty();
        }

        return $this->buildResult($this->ownershipResolver->resolve($elementTypes));
    }

    public function extractFromReleaseSnapshot(array $snapshot): ThemeExtensionDependencyExtractionResult
    {
        return $this->extractFromSnapshot(
            $snapshot['header_blocks'] ?? [],
            $snapshot['footer_blocks'] ?? [],
            $snapshot['pages'] ?? [],
        );
    }

    /** @param array<string, ElementOwnershipResult> $ownership */
    private function buildResult(array $ownership): ThemeExtensionDependencyExtractionResult
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
        $dependencies = [];
        foreach ($productToTypes as $productIdentity => $types) {
            sort($types, SORT_STRING);
            $dependencies[] = new ExtensionDependencyProposal($productIdentity, $types);
        }

        sort($unresolved, SORT_STRING);
        ksort($ambiguous, SORT_STRING);

        return new ThemeExtensionDependencyExtractionResult($dependencies, $unresolved, $ambiguous, $ownershipMap);
    }
}
