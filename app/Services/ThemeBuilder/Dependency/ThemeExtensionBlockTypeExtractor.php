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
        foreach ($this->collectInstances($headerBlocks, $footerBlocks, $pages) as $instance) {
            $found[$instance['type']] = true;
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

    /**
     * Phase 3G-B-6A (§13) — the same traversal as extractFromSnapshot(), but
     * every `ext:*` OCCURRENCE (not deduplicated by type), each carrying its
     * own authoring `id` (the existing stable per-block identity the Canvas
     * client already assigns — `blk_<timestamp>_<random>` — and re-generates
     * on any explicit clone/duplicate; see canvas.blade.php's `_regenIds()`).
     * A block with no `id` at all (malformed/legacy data) reports `id: null`
     * — callers must treat that as "cannot be proven historical," never as
     * a wildcard match.
     *
     * @param array $headerBlocks flat block list
     * @param array $footerBlocks flat block list
     * @param array $pages associative, keyed by page key => ['blocks' => [...], ...]
     * @return array<int, array{id: ?string, type: string}>
     */
    public function extractInstancesFromSnapshot(array $headerBlocks, array $footerBlocks, array $pages): array
    {
        return $this->collectInstances($headerBlocks, $footerBlocks, $pages);
    }

    /** @return array<int, array{id: ?string, type: string}> */
    private function collectInstances(array $headerBlocks, array $footerBlocks, array $pages): array
    {
        $instances = [];

        $this->scanBlockList($headerBlocks, $instances);
        $this->scanBlockList($footerBlocks, $instances);

        foreach ($pages as $page) {
            $blocks = is_array($page) ? ($page['blocks'] ?? []) : [];
            $this->scanBlockList(is_array($blocks) ? $blocks : [], $instances);
        }

        return $instances;
    }

    /** @param array<int, array{id: ?string, type: string}> $instances appended in place */
    private function scanBlockList(array $blocks, array &$instances): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if (is_string($type) && str_starts_with($type, self::EXT_PREFIX)) {
                $id = $block['id'] ?? null;
                $instances[] = ['id' => is_string($id) && $id !== '' ? $id : null, 'type' => $type];
            }

            $props = $block['props'] ?? $block['data'] ?? [];
            $props = is_array($props) ? $props : [];

            $children = $block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? []);
            if (is_array($children)) {
                $this->scanBlockList($children, $instances);
            }

            if (is_array($props['tabs'] ?? null)) {
                foreach ($props['tabs'] as $tab) {
                    $tabBlocks = is_array($tab) ? ($tab['blocks'] ?? []) : [];
                    $this->scanBlockList(is_array($tabBlocks) ? $tabBlocks : [], $instances);
                }
            }

            if (is_string($type) && $type === 'accordion' && is_array($props['items'] ?? null)) {
                foreach ($props['items'] as $item) {
                    if (is_array($item) && is_array($item['blocks'] ?? null)) {
                        $this->scanBlockList($item['blocks'], $instances);
                    }
                }
            }

            if (is_array($props['slides'] ?? null)) {
                $this->scanBlockList($props['slides'], $instances);
            }
        }
    }
}
