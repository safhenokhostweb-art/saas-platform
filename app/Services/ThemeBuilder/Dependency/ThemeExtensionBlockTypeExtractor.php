<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-3 — pure structural scanner: Theme snapshot → the deduplicated,
 * deterministically-ordered set of `ext:*` block TYPE strings used anywhere
 * in it. Identifies TYPES only — it never derives an owning Extension
 * product identity (that is ExtensionElementOwnershipResolver's job,
 * deliberately kept separate — see ADR-045's "never infer package identity
 * from ext:{vendor}:{block}" rule).
 *
 * Traversal rule is a byte-for-byte mirror of
 * App\Services\ThemeBuilder\QualityGate\Checks\RenderIntegrityCheck::
 * checkBlockList() — the one place in this codebase already proven (by
 * CanonicalBlockRegistryContractTest) to walk every real nested-block
 * container shape a SaaS Theme snapshot actually uses: `children`, `blocks`,
 * `props.blocks`, `props.tabs[].blocks`, `props.items[].blocks` (accordion),
 * and `props.slides[]` (the list itself is a nested block list). This
 * extractor must never drift out of sync with that traversal — if
 * RenderIntegrityCheck ever gains another nested-container shape, mirror it
 * here too, otherwise an Extension dependency could go silently undetected.
 *
 * No Eloquent, no framework dependency — pure arrays in, arrays out. No
 * fixed recursion depth. Malformed/unrelated structures (non-array blocks,
 * non-string types, non-array child containers) are skipped safely, never
 * thrown on — this is a best-effort structural scan, not a validator.
 */
final class ThemeExtensionBlockTypeExtractor
{
    private const EXT_PREFIX = 'ext:';

    /**
     * @param array $headerBlocks flat block list
     * @param array $footerBlocks flat block list
     * @param array $pages associative, keyed by page key => ['blocks' => [...], ...]
     * @return string[] unique `ext:*` type strings, sorted lexically (deterministic)
     */
    public function extractFromSnapshot(array $headerBlocks, array $footerBlocks, array $pages): array
    {
        $found = [];

        $this->scanBlockList($headerBlocks, $found);
        $this->scanBlockList($footerBlocks, $found);

        foreach ($pages as $page) {
            $blocks = is_array($page) ? ($page['blocks'] ?? []) : [];
            $this->scanBlockList(is_array($blocks) ? $blocks : [], $found);
        }

        $types = array_keys($found);
        sort($types, SORT_STRING);

        return $types;
    }

    /** Convenience wrapper over a ThemeRelease.snapshot array (header_blocks/footer_blocks/pages keys). */
    public function extractFromReleaseSnapshot(array $snapshot): array
    {
        return $this->extractFromSnapshot(
            $snapshot['header_blocks'] ?? [],
            $snapshot['footer_blocks'] ?? [],
            $snapshot['pages'] ?? [],
        );
    }

    /** @param array<string, true> $found mutated in place, keyed by ext:* type */
    private function scanBlockList(array $blocks, array &$found): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if (is_string($type) && str_starts_with($type, self::EXT_PREFIX)) {
                $found[$type] = true;
            }

            $props = $block['props'] ?? $block['data'] ?? [];
            $props = is_array($props) ? $props : [];

            $children = $block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? []);
            if (is_array($children)) {
                $this->scanBlockList($children, $found);
            }

            if (is_array($props['tabs'] ?? null)) {
                foreach ($props['tabs'] as $tab) {
                    $tabBlocks = is_array($tab) ? ($tab['blocks'] ?? []) : [];
                    $this->scanBlockList(is_array($tabBlocks) ? $tabBlocks : [], $found);
                }
            }

            if (is_string($type) && $type === 'accordion' && is_array($props['items'] ?? null)) {
                foreach ($props['items'] as $item) {
                    if (is_array($item) && is_array($item['blocks'] ?? null)) {
                        $this->scanBlockList($item['blocks'], $found);
                    }
                }
            }

            if (is_array($props['slides'] ?? null)) {
                $this->scanBlockList($props['slides'], $found);
            }
        }
    }
}
