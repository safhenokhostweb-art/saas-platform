<?php

namespace SaaSPlatform\ThemeRuntime\Access;

use SaaSPlatform\ThemeRuntime\TemplateLibrary;

/**
 * ADR-087: what both canvas editors call. Listing (template cards, widget
 * palette) drops hidden assets and marks locked ones; writing (template
 * insert, saving a block tree) refuses a locked asset. Rendering is never
 * gated: a page that already holds an asset keeps rendering it, so a
 * revoked or lapsed licence stops further use without breaking a live
 * site (see ADR-087 Consequences).
 */
final class AssetAccessGate
{
    /** The template-library key of a card id ("lib:solar-header"), or null for a saved template. */
    public static function libraryKey(mixed $id): ?string
    {
        return TemplateLibrary::isLibraryId($id) ? substr((string) $id, strlen(TemplateLibrary::ID_PREFIX)) : null;
    }

    /** Null for a saved (site- or platform-authored) template: not a platform asset. */
    public static function decideTemplate(mixed $id, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        $key = self::libraryKey($id);

        return $key === null ? null : AssetAccessCatalog::decide(AssetRef::KIND_TEMPLATE, $key, $site);
    }

    /** Null for an ungoverned host/extension widget type. */
    public static function decideWidget(string $type, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        return AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, $type, $site);
    }

    public static function decideButtonPack(string $slug, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        return AssetAccessCatalog::decide(AssetRef::KIND_BUTTON_PACK, $slug, $site);
    }

    public static function decideIconPack(string $slug, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        return AssetAccessCatalog::decide(AssetRef::KIND_ICON_PACK, $slug, $site);
    }

    public static function decideHeader(string $slug, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        return AssetAccessCatalog::decide(AssetRef::KIND_HEADER, $slug, $site);
    }

    public static function decideMotion(string $slug, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        return AssetAccessCatalog::decide(AssetRef::KIND_MOTION, $slug, $site);
    }

    /**
     * Theme scopes unlocked by site entitlements or explicit site_asset_grants.
     *
     * @return list<string>
     */
    public static function unlockedThemeScopes(SiteAssetEntitlements $site): array
    {
        $scopes = [];
        $solarCalcAllowed = self::decideWidget('solar_calculator', $site)?->allowed() ?? false;
        $solarCycleAllowed = self::decideWidget('solar-energy-cycle', $site)?->allowed() ?? false;

        if ($solarCalcAllowed || $solarCycleAllowed) {
            $scopes[] = 'solar-industry';
        }

        return $scopes;
    }

    /**
     * Template-modal cards: hidden library cards are dropped, every other
     * library card gains `access` (AssetAccessDecision::toArray()).
     *
     * @param list<array<string, mixed>> $cards
     * @return list<array<string, mixed>>
     */
    public static function annotateTemplateCards(array $cards, SiteAssetEntitlements $site): array
    {
        $out = [];
        foreach ($cards as $card) {
            $decision = self::decideTemplate($card['id'] ?? null, $site);
            if ($decision !== null && !$decision->visible()) {
                continue;
            }
            if ($decision !== null) {
                $card['access'] = $decision->toArray();
            }
            $out[] = $card;
        }

        return $out;
    }

    /**
     * The palette (category => type => definition) without hidden widgets,
     * plus the access map of every governed listed widget. The full
     * registry is left alone on purpose: an existing block of a type that
     * is now hidden must still open in the inspector.
     *
     * @param array<string, array<string, array>> $byCategory
     * @return array{0: array<string, array<string, array>>, 1: array<string, array>}
     */
    public static function filterPalette(array $byCategory, SiteAssetEntitlements $site): array
    {
        $access = [];
        foreach ($byCategory as $category => $types) {
            foreach (array_keys($types) as $type) {
                $decision = self::decideWidget((string) $type, $site);
                if ($decision === null) {
                    continue;
                }
                if (!$decision->visible()) {
                    unset($byCategory[$category][$type]);
                    continue;
                }
                if ($decision->restricted) {
                    $access[$type] = $decision->toArray();
                }
            }
            if ($byCategory[$category] === []) {
                unset($byCategory[$category]);
            }
        }

        return [$byCategory, $access];
    }

    /**
     * Governed widget types in a block tree the site may not use.
     *
     * @return list<string>
     */
    public static function lockedWidgetTypes(array $blocks, SiteAssetEntitlements $site): array
    {
        $locked = [];
        foreach (self::widgetTypes($blocks) as $type) {
            $decision = self::decideWidget($type, $site);
            if ($decision !== null && !$decision->allowed()) {
                $locked[] = $type;
            }
        }

        return $locked;
    }

    /**
     * Locked widget types the incoming tree introduces. A type already in
     * the stored tree is accepted unchanged, so a site whose licence lapsed
     * can still edit and save the rest of the page.
     *
     * @return list<string>
     */
    public static function newlyLockedWidgetTypes(array $before, array $after, SiteAssetEntitlements $site): array
    {
        $existing = array_flip(self::widgetTypes($before));

        return array_values(array_filter(
            self::lockedWidgetTypes($after, $site),
            static fn (string $type): bool => !isset($existing[$type]),
        ));
    }

    /**
     * Every block type in a tree: `children`, `blocks` and block-shaped
     * lists inside `props` (section/slider bodies).
     *
     * @return list<string>
     */
    public static function widgetTypes(array $blocks): array
    {
        $types = [];
        $walk = function (array $list) use (&$walk, &$types): void {
            foreach ($list as $block) {
                if (!is_array($block)) {
                    continue;
                }
                if (is_string($block['type'] ?? null) && $block['type'] !== '') {
                    $types[$block['type']] = true;
                }
                foreach (['children', 'blocks'] as $key) {
                    if (is_array($block[$key] ?? null)) {
                        $walk($block[$key]);
                    }
                }
                foreach (is_array($block['props'] ?? null) ? $block['props'] : [] as $value) {
                    if (is_array($value) && array_is_list($value) && self::looksLikeBlocks($value)) {
                        $walk($value);
                    }
                }
            }
        };
        $walk($blocks);

        return array_keys($types);
    }

    private static function looksLikeBlocks(array $list): bool
    {
        foreach ($list as $item) {
            if (is_array($item) && is_string($item['type'] ?? null) && (isset($item['props']) || isset($item['children']) || isset($item['id']))) {
                return true;
            }
        }

        return false;
    }
}
