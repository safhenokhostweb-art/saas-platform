<?php

namespace SaaSPlatform\ThemeRuntime\Access;

use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;

/**
 * ADR-087: reads the central access policy this package ships,
 * resources/access/policy.json, the declaration of every library template
 * and every canonical widget. It is code, versioned and reviewed with the
 * assets themselves, and byte-identical on the platform and every tenant.
 *
 * The effective rule of an asset is, in order: the platform's per-asset
 * override (carried by SiteAssetEntitlements), then the manifest entry,
 * then failClosed(). A library template or canonical widget missing from
 * the manifest is therefore grant-only and never listed, so a specialized
 * asset cannot become public by being forgotten.
 *
 * Host-only and extension widget types (customer-site `column`, `ext:*`)
 * are not governed here unless listed: their hosts gate them through
 * their own capability and extension-entitlement contracts.
 */
final class AssetAccessCatalog
{
    public const PUBLIC = 'public';

    /** @var array{families: array<string, array>, templates: array<string, string>, widgets: array<string, string>}|null */
    private static ?array $manifest = null;

    public static function path(): string
    {
        return dirname(__DIR__, 2) . '/resources/access/policy.json';
    }

    /** The manifest rule, or null when the asset is not listed. */
    public static function declaredRule(string $kind, string $key): ?AssetAccessRule
    {
        $entry = self::section($kind)[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry === self::PUBLIC) {
            return AssetAccessRule::public();
        }
        $family = self::manifest()['families'][$entry] ?? null;
        if (!is_array($family)) {
            // A typo'd family name never publishes the asset.
            return AssetAccessRule::failClosed()->withFamily($entry, null);
        }

        return AssetAccessRule::fromArray($family, $entry);
    }

    /**
     * The effective rule, or null for an ungoverned host/extension widget.
     */
    public static function rule(string $kind, string $key, ?SiteAssetEntitlements $site = null): ?AssetAccessRule
    {
        if (!self::isGoverned($kind, $key)) {
            return null;
        }
        $declared = self::declaredRule($kind, $key) ?? AssetAccessRule::failClosed();
        $override = $site?->override(AssetRef::make($kind, $key));
        if ($override !== null) {
            return $override->withFamily($declared->family, $declared->familyLabel);
        }

        return $declared;
    }

    public static function decide(string $kind, string $key, SiteAssetEntitlements $site): ?AssetAccessDecision
    {
        $rule = self::rule($kind, $key, $site);

        return $rule === null ? null : AssetAccessPolicy::decide($rule, AssetRef::make($kind, $key), $site);
    }

    /**
     * Templates: every library document. Widgets: every listed type and
     * every canonical type (listed or not).
     */
    public static function isGoverned(string $kind, string $key): bool
    {
        return match ($kind) {
            AssetRef::KIND_TEMPLATE => isset(self::section($kind)[$key]) || TemplateLibrary::isLibraryId(TemplateLibrary::ID_PREFIX . $key),
            AssetRef::KIND_WIDGET => isset(self::section($kind)[$key]) || BlockRegistry::isCanonicalType($key),
            AssetRef::KIND_MOTION,
            AssetRef::KIND_HEADER,
            AssetRef::KIND_BUTTON_PACK,
            AssetRef::KIND_ICON_PACK => isset(self::section($kind)[$key]),
            default => false,
        };
    }

    /** @return array<string, string> key => "public" | family */
    public static function section(string $kind): array
    {
        return match ($kind) {
            AssetRef::KIND_TEMPLATE => self::manifest()['templates'] ?? [],
            AssetRef::KIND_WIDGET => self::manifest()['widgets'] ?? [],
            AssetRef::KIND_MOTION => self::manifest()['motions'] ?? [],
            AssetRef::KIND_HEADER => self::manifest()['headers'] ?? [],
            AssetRef::KIND_BUTTON_PACK => self::manifest()['button_packs'] ?? [],
            AssetRef::KIND_ICON_PACK => self::manifest()['icon_packs'] ?? [],
            default => [],
        };
    }

    /** @return array<string, array> family => raw rule */
    public static function families(): array
    {
        return self::manifest()['families'];
    }

    /**
     * Every governed asset that is not public in the manifest, for the
     * platform panel: ref => [kind, key, family, label, rule].
     *
     * @return array<string, array{kind: string, key: string, family: ?string, name: string, rule: AssetAccessRule}>
     */
    public static function restrictedAssets(): array
    {
        $out = [];
        foreach (AssetRef::KINDS as $kind) {
            foreach (self::section($kind) as $key => $entry) {
                $rule = self::declaredRule($kind, $key);
                if ($rule === null || $rule->isPublic) {
                    continue;
                }
                $out[AssetRef::make($kind, $key)] = ['kind' => $kind, 'key' => $key, 'family' => $rule->family, 'name' => self::displayName($kind, $key), 'rule' => $rule];
            }
        }

        return $out;
    }

    /**
     * Every listed asset (public ones too), for the platform's override
     * form: ref => display name.
     *
     * @return array<string, string>
     */
    public static function listedAssets(): array
    {
        $out = [];
        foreach (AssetRef::KINDS as $kind) {
            foreach (array_keys(self::section($kind)) as $key) {
                $out[AssetRef::make($kind, $key)] = self::displayName($kind, $key);
            }
        }

        return $out;
    }

    public static function displayName(string $kind, string $key): string
    {
        if ($kind === AssetRef::KIND_TEMPLATE) {
            foreach (TemplateLibrary::list() as $card) {
                if ($card['id'] === TemplateLibrary::ID_PREFIX . $key) {
                    return $card['name'] . ' (' . $card['type_label'] . ')';
                }
            }
        }
        if ($kind === AssetRef::KIND_WIDGET && ($def = BlockRegistry::get($key)) !== null) {
            return (string) ($def['name'] ?? $key);
        }

        return $key;
    }

    /** Test-only. */
    public static function flush(): void
    {
        self::$manifest = null;
    }

    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $raw = is_file(self::path()) ? json_decode((string) file_get_contents(self::path()), true) : null;
        // An unreadable manifest governs nothing as public: every template
        // and canonical widget falls back to failClosed().
        $ok = is_array($raw) && ($raw['schema'] ?? null) === 1;

        return self::$manifest = [
            'families' => $ok && is_array($raw['families'] ?? null) ? $raw['families'] : [],
            'templates' => $ok ? self::entries($raw['templates'] ?? null) : [],
            'widgets' => $ok ? self::entries($raw['widgets'] ?? null) : [],
        ];
    }

    /** @return array<string, string> */
    private static function entries(mixed $raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $key => $entry) {
            if (is_string($key) && is_string($entry) && $entry !== '') {
                $out[$key] = $entry;
            }
        }

        return $out;
    }
}
