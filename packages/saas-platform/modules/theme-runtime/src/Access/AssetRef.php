<?php

namespace SaaSPlatform\ThemeRuntime\Access;

/**
 * ADR-087: the one spelling of a platform asset's identity, "<kind>:<key>",
 * shared by the policy manifest, platform grants and overrides, the
 * provisioning payload and both hosts' editors.
 *
 * Kinds are the asset families this package governs. Motions are not a
 * kind here: ADR-086 governs them with the same public/restricted + grant
 * model inside the motion manifest, and they reach a site only through it.
 * A new kind (a future asset family) is added here, in the manifest and in
 * AssetAccessCatalog, never by a host on its own.
 */
final class AssetRef
{
    public const KIND_TEMPLATE = 'template';

    public const KIND_WIDGET = 'widget';

    public const KINDS = [self::KIND_TEMPLATE, self::KIND_WIDGET];

    public const KIND_LABELS = [
        self::KIND_TEMPLATE => 'قالب آماده (صفحه، بخش، هدر، فوتر)',
        self::KIND_WIDGET => 'ویجت',
    ];

    /** Library keys and widget types, e.g. "solar-header", "module:motion", "solar_calculator". */
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9_:\-]{0,119}$/';

    public static function make(string $kind, string $key): string
    {
        if (!self::isValidPart($kind, $key)) {
            throw new \InvalidArgumentException("Invalid asset reference [{$kind}:{$key}].");
        }

        return $kind . ':' . $key;
    }

    public static function isValid(mixed $ref): bool
    {
        return self::parse($ref) !== null;
    }

    /** @return array{0: string, 1: string}|null [kind, key] */
    public static function parse(mixed $ref): ?array
    {
        if (!is_string($ref)) {
            return null;
        }
        $pos = strpos($ref, ':');
        if ($pos === false) {
            return null;
        }
        $kind = substr($ref, 0, $pos);
        $key = substr($ref, $pos + 1);

        return self::isValidPart($kind, $key) ? [$kind, $key] : null;
    }

    private static function isValidPart(string $kind, string $key): bool
    {
        return in_array($kind, self::KINDS, true) && preg_match(self::KEY_PATTERN, $key) === 1;
    }
}
