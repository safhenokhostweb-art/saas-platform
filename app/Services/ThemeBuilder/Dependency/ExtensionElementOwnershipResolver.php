<?php
namespace App\Services\ThemeBuilder\Dependency;

use App\Models\ExtensionRelease;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use Throwable;

/**
 * Phase 3G-B-3 — the ONLY authority allowed to answer "which Extension
 * PRODUCT owns this `ext:*` element type?" Never infers a product identity
 * from the type string itself (the block identity `ext:{vendor}:{block}`
 * carries no package slug — ADR-045's explicit prohibition). The sole
 * source of truth is declarative: every ExtensionRelease's own validated
 * `ExtensionManifest.elements[*].type` set, across every Extension product.
 *
 * Historical-ownership policy (audited, not invented): ownership evidence is
 * gathered from EVERY ExtensionRelease row ever published for a product —
 * not merely `Extension.currentRelease` — because 3G-B-1 deliberately
 * preserved historical release resolvability (a superseded/deprecated
 * release row is never deleted, per ExtensionRelease's own immutable-ledger
 * guarantee), and a Theme published against an old Extension release must
 * remain attributable to that same product even after the Extension's
 * current_release_id moves on to a release that no longer includes that
 * element type. Superseded/deprecated releases are legitimate historical
 * evidence, not discarded ones.
 *
 * Collision policy (audited gap, reported honestly): saas-platform does not
 * currently prevent two different Extension products from each publishing a
 * manifest that claims the same `ext:*` element type (no such uniqueness
 * guard exists in ExtensionReleaseService/ExtensionManifest today — see the
 * 3G-B-3 audit report). This resolver therefore treats that as a real,
 * possible outcome — AMBIGUOUS — rather than assuming uniqueness. It never
 * arbitrarily picks the newest, first, or highest-id owner.
 *
 * A stored manifest that fails to re-parse via ExtensionManifest::fromArray()
 * contributes zero ownership evidence — it is never treated as a valid
 * authority (defensive; every manifest already passed this exact validation
 * at ExtensionReleaseService::release() write time, so this should never
 * trigger in practice, but ownership resolution must fail closed, not
 * throw, on a hypothetically corrupted row).
 *
 * Only ever queries saas-platform's own commercial catalog
 * (Extension/ExtensionRelease). Never executes third-party provider code,
 * never queries customer-site, never imports customer-site App\* classes.
 */
final class ExtensionElementOwnershipResolver
{
    /**
     * @param string[] $elementTypes
     * @return array<string, ElementOwnershipResult> keyed by element type, same members as input
     */
    public function resolve(array $elementTypes): array
    {
        if ($elementTypes === []) {
            return [];
        }

        $requested = array_flip($elementTypes);
        /** @var array<string, array<string, true>> $ownersByType element type => {productIdentity => true} */
        $ownersByType = [];

        ExtensionRelease::with('extension')->chunkById(200, function ($releases) use ($requested, &$ownersByType) {
            foreach ($releases as $release) {
                $manifest = $this->tryParseManifest($release);
                if ($manifest === null) {
                    continue;
                }

                $productIdentity = $release->extension?->identifier();
                if ($productIdentity === null) {
                    continue;
                }

                foreach (array_keys($manifest->elements) as $type) {
                    if (isset($requested[$type])) {
                        $ownersByType[$type][$productIdentity] = true;
                    }
                }
            }
        });

        $results = [];
        foreach ($elementTypes as $type) {
            $owners = array_keys($ownersByType[$type] ?? []);
            sort($owners, SORT_STRING);

            $results[$type] = match (count($owners)) {
                0 => ElementOwnershipResult::unresolved($type),
                1 => ElementOwnershipResult::resolved($type, $owners[0]),
                default => ElementOwnershipResult::ambiguous($type, $owners),
            };
        }

        return $results;
    }

    private function tryParseManifest(ExtensionRelease $release): ?ExtensionManifest
    {
        try {
            return ExtensionManifest::fromArray($release->manifest);
        } catch (Throwable) {
            return null;
        }
    }
}
