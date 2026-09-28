<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/**
 * Phase 2A — the ONE canonical layout/direction/alignment contract for the
 * Canvas commercial builder, promoted verbatim from the Phase 1 test
 * prototype (tests/Unit/Architecture/LayoutContract.php) into the shared
 * theme-runtime package so both hosts consume one implementation (the same
 * single-authority discipline as BlockRegistry/BlockRenderer).
 *
 * This class owns pure data contracts only: node kinds, direction and
 * logical-alignment vocabularies, responsive breakpoints, and the lossless
 * legacy block-tree ⇄ canonical node-tree adapter. It renders nothing,
 * queries nothing, and never touches host data — MODULE owns data, THEME
 * owns presentation, CANVAS owns composition (this class is composition
 * vocabulary).
 *
 * Alignment is stored LOGICALLY (start|center|end) — never physically.
 * Physical resolution happens only at the presentation boundary via
 * resolveAlignment()/resolveFlexAlign() with an explicit direction.
 */
final class LayoutContract
{
    // ── Node kinds ──────────────────────────────────────────────────────
    public const NODE_KINDS = ['page', 'section', 'container', 'row', 'column', 'widget'];
    public const STRUCTURE_KINDS = ['section', 'container', 'row', 'column'];
    public const LEAF_KINDS = ['widget'];

    /** Layout document schema version (see DocumentEnvelope). */
    public const LAYOUT_VERSION = 1;

    // ── Direction (first-class data, never a CSS-only side effect) ──────
    public const DIRECTIONS = ['rtl', 'ltr'];
    public const DEFAULT_DIRECTION = 'rtl';
    private const RTL_LOCALES = ['fa', 'fa_ir', 'ar', 'ar_eg', 'ar_sa', 'ur', 'he'];

    // ── Logical alignment (never store left/right) ──────────────────────
    public const ALIGNMENTS = ['start', 'center', 'end'];

    // ── Responsive breakpoints ──────────────────────────────────────────
    public const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

    /**
     * Media-query emission order (narrowest last) and per-name max-widths,
     * matching the existing renderer convention (desktop base +
     * max-width:1024px tablet + max-width:480px mobile).
     */
    public const BREAKPOINT_MAX_WIDTHS = [1024, 480];
    public const BREAKPOINT_MAX_WIDTH_BY_NAME = ['tablet' => 1024, 'mobile' => 480];

    public static function isSupportedDirection(string $direction): bool
    {
        return in_array($direction, self::DIRECTIONS, true);
    }

    /** Locale → direction. Unknown locales fail closed to the platform default. */
    public static function directionForLocale(string $locale): string
    {
        $normalized = strtolower(str_replace('-', '_', trim($locale)));
        $primary = explode('_', $normalized)[0];

        if (in_array($normalized, self::RTL_LOCALES, true) || in_array($primary, self::RTL_LOCALES, true)) {
            return 'rtl';
        }
        if ($primary === 'en') {
            return 'ltr';
        }

        return self::DEFAULT_DIRECTION;
    }

    /**
     * Normalize any alignment input (logical or legacy physical) to a
     * logical value. The ONLY sanctioned place a physical left/right
     * synonym is interpreted — and interpretation requires the document
     * direction.
     */
    public static function normalizeAlignment(string $value, string $direction): string
    {
        if (in_array($value, self::ALIGNMENTS, true)) {
            return $value;
        }

        // Legacy physical synonyms (import-compat only).
        return match ($value) {
            'left'  => $direction === 'rtl' ? 'end' : 'start',
            'right' => $direction === 'rtl' ? 'start' : 'end',
            default => 'start',
        };
    }

    /** Logical → physical resolution for CSS `text-align`-style properties. */
    public static function resolveAlignment(string $logical, string $direction): string
    {
        return match (self::normalizeAlignment($logical, $direction)) {
            'center' => 'center',
            'end'    => $direction === 'rtl' ? 'left' : 'right',
            default  => $direction === 'rtl' ? 'right' : 'left',
        };
    }

    /**
     * Logical → flex keywords for justify/align properties. Flex
     * start/end are inherently direction-aware in CSS, so no physical
     * value is ever produced.
     */
    public static function resolveFlexAlign(string $logical, string $direction): string
    {
        // Deliberately direction-free output: flex start/end keywords are
        // inherently direction-aware in CSS, so the direction argument is
        // accepted for call-signature symmetry but never used to pick a
        // physical value.
        return match (self::normalizeAlignment($logical, 'rtl')) {
            'center' => 'center',
            'end'    => 'flex-end',
            default  => 'flex-start',
        };
    }

    /**
     * Cascade resolution: the requested breakpoint's override, else the
     * nearest wider breakpoint's, else the desktop base, else $default.
     * (Mobile → tablet → desktop, mirroring the editor's existing
     * getCascadedValue() behavior.)
     */
    public static function responsiveValue(array $responsive, string $breakpoint, string $key, mixed $default = null): mixed
    {
        if (!in_array($breakpoint, self::BREAKPOINTS, true)) {
            return $default;
        }

        $chain = match ($breakpoint) {
            'mobile'  => ['mobile', 'tablet', 'desktop'],
            'tablet'  => ['tablet', 'desktop'],
            default   => ['desktop'],
        };

        foreach ($chain as $bp) {
            $value = $responsive[$bp][$key] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    public static function isResponsiveShape(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $bp => $overrides) {
            if (!in_array($bp, self::BREAKPOINTS, true) || !is_array($overrides)) {
                return false;
            }
        }

        return true;
    }

    // ── Node validation ─────────────────────────────────────────────────

    public static function isNodeDocument(mixed $node): bool
    {
        if (!is_array($node)) {
            return false;
        }

        $id = $node['id'] ?? null;
        if (!is_string($id) || $id === '') {
            return false;
        }

        $kind = $node['kind'] ?? null;
        if (!in_array($kind, self::NODE_KINDS, true)) {
            return false;
        }

        foreach (['props', 'style', 'responsive', 'children'] as $arrayKey) {
            if (array_key_exists($arrayKey, $node) && !is_array($node[$arrayKey])) {
                return false;
            }
        }

        // Widgets are leaves by contract.
        if ($kind === 'widget' && array_key_exists('children', $node)) {
            return false;
        }

        return true;
    }

    // ── Adapter: legacy block tree ⇄ canonical node tree ─────────────────
    //
    // LOSSLESS by contract: ids, props, style, responsive, dataBinding,
    // modulePresentation, and every unknown future key round-trip
    // byte-identically — and a block that never carried props/style/
    // responsive does not gain empty keys. The adapter NEVER drops or
    // renames author data; its two intentional normalizations are
    // canonicalizing the legacy children shapes (children / blocks /
    // props.blocks → children) and the legacy props alias (data → props,
    // which the renderer already reads via $block['props'] ?? $block['data']).

    public static function fromLegacyBlocks(array $blocks): array
    {
        return [
            'id' => 'page',
            'kind' => 'page',
            'children' => self::adaptNodes($blocks),
        ];
    }

    /**
     * Convert a page node (or a bare list of nodes) back to the legacy
     * block-tree shape. Accepts either envelope so callers can pass
     * fromLegacyBlocks() output directly or a nodes list.
     */
    public static function toLegacyBlocks(array $pageNode): array
    {
        if (($pageNode['kind'] ?? null) !== 'page' && !array_is_list($pageNode)) {
            return [];
        }

        $nodes = ($pageNode['kind'] ?? null) === 'page'
            ? ($pageNode['children'] ?? [])
            : $pageNode;

        return self::toLegacyNodes($nodes);
    }

    private static function adaptNodes(array $blocks): array
    {
        $nodes = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $children = $block['children']
                ?? $block['blocks']
                ?? $block['props']['blocks']
                ?? [];

            // Key order mirrors the legacy block (id, type, props, style,
            // responsive, children) with `kind` emitted after the legacy
            // keys so a round trip preserves the original ordering exactly.
            // props/style/responsive are copied only when the legacy block
            // actually carried them: a block that never had a key must not
            // GAIN an empty one (true losslessness). `data` is the legacy
            // props alias read by the renderer ($block['props'] ??
            // $block['data']) and is canonicalized to `props` — the one
            // intentional normalization alongside children-shape folding.
            $node = [
                'id' => (string) ($block['id'] ?? ''),
                'type' => $type,
            ];

            if (isset($block['props']) && is_array($block['props'])) {
                $node['props'] = $block['props'];
            } elseif (isset($block['data']) && is_array($block['data'])) {
                $node['props'] = $block['data'];
            }
            if (isset($block['style']) && is_array($block['style'])) {
                $node['style'] = $block['style'];
            }
            if (isset($block['responsive']) && is_array($block['responsive'])) {
                $node['responsive'] = $block['responsive'];
            }
            $extra = [];
            foreach ($block as $key => $value) {
                if (array_key_exists($key, $node)
                    || in_array($key, ['blocks', 'data'], true)) {
                    continue;
                }
                $extra[$key] = $value;
            }
            foreach ($extra as $key => $value) {
                $node[$key] = $value;
            }

            // Composition follows data: a legacy block that already carries
            // children IS a container in canonical form, regardless of its
            // type vocabulary — host/extension containers (ext:*) keep their
            // subtree without this class ever needing host-specific type
            // knowledge. (The Phase 1 leaf rule stays strict: a canonical
            // widget NEVER carries children — anything that composes is a
            // container.)
            $kind = self::kindForType($type);
            $carriesChildren = (isset($block['children']) && is_array($block['children']))
                || (is_array($children) && $children !== []);
            if ($kind === 'widget' && $carriesChildren) {
                $kind = 'container';
            }
            $node['kind'] = $kind;

            if (isset($block['children']) && is_array($block['children'])) {
                $node['children'] = self::adaptNodes($block['children']);
            } elseif ($children !== [] && is_array($children)) {
                $node['children'] = self::adaptNodes($children);
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Phase 2B-R — legacy identity compatibility at the editor boundary.
     *
     * Themes seeded before canonical ids existed (and JSON authored by older
     * tooling) may carry blocks with no `id` at all. The canonical document
     * validator is fail-closed about missing node identity, which would make
     * every such legacy document unsaveable through the Canvas. The editor
     * boundary therefore derives a stable, deterministic id for exactly the
     * id-less blocks: `blk_<sha1-12>` over identity-relevant content (type,
     * position, props, style, responsive, child shape). The same legacy tree
     * always derives the same ids, so a save/reload round trip is stable.
     *
     * Blocks that already carry an id are passed through untouched — no
     * re-keying, no storage rewrite, no migration. Non-array entries are
     * preserved verbatim for the validator to judge. Use SYMMETRICALLY on
     * both the client tree and the stored before-tree so validation and
     * structural guarding compare like with like.
     */
    public static function withDerivedLegacyIds(array $blocks): array
    {
        $derive = null;
        $derive = static function (array $items, int $depth = 0) use (&$derive): array {
            $out = [];
            $position = 0;
            foreach ($items as $block) {
                if (!is_array($block)) {
                    $out[] = $block;
                    $position++;
                    continue;
                }

                $id = $block['id'] ?? null;
                if (!is_string($id) || $id === '') {
                    $children = is_array($block['children'] ?? null)
                        ? $block['children']
                        : (is_array($block['blocks'] ?? null) ? $block['blocks'] : []);
                    // Identity must remain stable while editable content,
                    // style, responsive overrides, or bindings change. Only
                    // structural coordinates and type participate; otherwise
                    // a normal Inspector edit would look like delete+insert
                    // to the server-side structural transition validator.
                    $fingerprint = [
                        'type' => $block['type'] ?? null,
                        'position' => $position,
                        'depth' => $depth,
                        'children' => array_map(
                            static fn ($c): array => is_array($c) ? [
                                'type' => $c['type'] ?? null,
                            ] : [],
                            $children
                        ),
                    ];
                    $id = 'blk_' . substr(sha1(json_encode(
                        $fingerprint,
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    )), 0, 12);
                }
                $block['id'] = $id;

                if ($depth < 16) {
                    foreach (['children', 'blocks'] as $nestedKey) {
                        if (isset($block[$nestedKey]) && is_array($block[$nestedKey])) {
                            $block[$nestedKey] = $derive($block[$nestedKey], $depth + 1);
                        }
                    }
                }

                $out[] = $block;
                $position++;
            }

            return $out;
        };

        return $derive($blocks);
    }

    private static function toLegacyNodes(array $nodes): array
    {
        $blocks = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || ($node['kind'] ?? null) === 'page') {
                continue;
            }

            // Emit keys in the NODE's own order (kind is the only layout-only
            // key dropped), so a round trip preserves the original key order
            // and every future/extra key verbatim. Legacy 'blocks'/'data'
            // shapes were canonicalized to 'children'/'props' by the adapter
            // and stay canonicalized here — that is the one intentional
            // normalization.
            $block = [];
            foreach ($node as $key => $value) {
                if ($key === 'kind') {
                    continue;
                }
                if ($key === 'children') {
                    if (in_array($node['kind'] ?? '', self::STRUCTURE_KINDS, true) && is_array($value)) {
                        $block['children'] = self::toLegacyNodes($value);
                    }
                    continue;
                }
                $block[$key] = $value;
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Legacy type → layout kind. Mirrors the canonical structural
     * container list (BlockRenderer::isStructuralContainer / the registry's
     * is_container types): identity names keep their kind; aliases map to
     * 'container'; everything else is a widget. row/column are reserved
     * kinds the layout engine will introduce — existing content never
     * breaks because columns/flex_container map to 'container'.
     *
     * Note: this is the TYPE-vocabulary view only. adaptNodes() upgrades a
     * widget-typed block that actually carries children to 'container'
     * (composition follows data), so host/extension containers need no
     * entry here.
     */
    public static function kindForType(string $type): string
    {
        return match ($type) {
            'section', 'container', 'row', 'column' => $type,
            'grid', 'columns', 'flex_container', 'modal', 'tabs', 'tab_panel' => 'container',
            default => 'widget',
        };
    }
}
