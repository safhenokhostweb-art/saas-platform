<?php
namespace App\Services\ThemeBuilder\Dependency;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use Throwable;

/**
 * Phase 3G-B-4 — the ONLY authority allowed to answer "what Extension
 * release version did Theme authoring truthfully select for this product,
 * and can that selection be trusted?" Never falls back to
 * `Extension.current_release_id` (that is only ever the current CATALOG
 * pointer, not an authoring guarantee that a specific Theme was authored or
 * tested against it — ADR-045 point-of-fact, re-confirmed by this phase's
 * audit) and never fabricates a range/wildcard constraint.
 *
 * V1 constraint policy: EXACT version only (`=X.Y.Z`). No compatibility
 * range (`>=`, `^`) exists anywhere in the Extension SDK contract today —
 * `ExtensionSdkContract` is a plain integer contract-version vocabulary,
 * never a semver compatibility declaration — so a broader constraint would
 * fabricate a forward/backward-compatibility guarantee nothing in this
 * codebase actually makes. "This ThemeRelease was authored against exactly
 * Extension release X.Y.Z" is the only truthful V1 statement.
 *
 * Release-status policy: ANY existing ExtensionRelease row for the exact
 * product+version is accepted regardless of published/superseded/deprecated
 * status — all three are equally valid, immutable, historically-published
 * ledger entries (identical reasoning to ExtensionElementOwnershipResolver's
 * historical-evidence policy). Status only ever matters for whether a
 * version should be OFFERED for a *new* selection in a future authoring UI
 * (B-6) — this resolver only validates an already-recorded selection.
 *
 * A selection is only ever represented as `{product_identity: version}` —
 * never a raw ExtensionRelease id — so "a selected release belongs to a
 * different product" is structurally impossible: the release lookup is
 * always scoped to the Extension resolved from the product identity itself.
 */
final class ExtensionVersionSelectionResolver
{
    /**
     * @param array<string, string> $selections productIdentity => selected version string
     * @param array<string, string[]> $requiredElementsByProduct productIdentity => element types this Theme actually uses from it
     * @return array<string, ExtensionVersionSelectionResult> keyed by product identity, one entry per key in $requiredElementsByProduct
     */
    public function resolve(array $selections, array $requiredElementsByProduct): array
    {
        $results = [];

        foreach ($requiredElementsByProduct as $productIdentity => $requiredElementTypes) {
            $results[$productIdentity] = $this->resolveOne($productIdentity, $selections[$productIdentity] ?? null, $requiredElementTypes);
        }

        return $results;
    }

    /** @param string[] $requiredElementTypes */
    private function resolveOne(string $productIdentity, ?string $selectedVersion, array $requiredElementTypes): ExtensionVersionSelectionResult
    {
        if ($selectedVersion === null || $selectedVersion === '') {
            return ExtensionVersionSelectionResult::unresolved($productIdentity);
        }

        $extension = $this->findExtension($productIdentity);
        if ($extension === null) {
            return ExtensionVersionSelectionResult::invalid($productIdentity, $selectedVersion, "no Extension product \"{$productIdentity}\" exists.");
        }

        /** @var ExtensionRelease|null $release */
        $release = $extension->releases()->where('version', $selectedVersion)->first();
        if ($release === null) {
            return ExtensionVersionSelectionResult::invalid(
                $productIdentity,
                $selectedVersion,
                "Extension \"{$productIdentity}\" has no release version \"{$selectedVersion}\"."
            );
        }

        $manifest = $this->tryParseManifest($release);
        if ($manifest === null) {
            return ExtensionVersionSelectionResult::invalid(
                $productIdentity,
                $selectedVersion,
                "the manifest for \"{$productIdentity}\" release \"{$selectedVersion}\" is not a valid ExtensionManifest."
            );
        }

        foreach ($requiredElementTypes as $type) {
            if (!array_key_exists($type, $manifest->elements)) {
                return ExtensionVersionSelectionResult::invalid(
                    $productIdentity,
                    $selectedVersion,
                    "the selected release \"{$selectedVersion}\" of \"{$productIdentity}\" does not provide the used element type \"{$type}\"."
                );
            }
        }

        return ExtensionVersionSelectionResult::resolved($productIdentity, $selectedVersion, "={$selectedVersion}");
    }

    private function findExtension(string $productIdentity): ?Extension
    {
        if (!ExtensionProductIdentity::isValidIdentifier($productIdentity)) {
            return null;
        }

        $identity = ExtensionProductIdentity::fromString($productIdentity);

        return Extension::where('vendor_slug', $identity->vendorSlug())
            ->where('package_slug', $identity->packageSlug())
            ->first();
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
