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

    public const KIND_MOTION = 'motion';

    public const KIND_HEADER = 'header';

    public const KIND_BUTTON_PACK = 'button_pack';

    public const KIND_ICON_PACK = 'icon_pack';

    public const KINDS = [
        self::KIND_TEMPLATE,
        self::KIND_WIDGET,
        self::KIND_MOTION,
        self::KIND_HEADER,
        self::KIND_BUTTON_PACK,
        self::KIND_ICON_PACK,
    ];

    public const KIND_LABELS = [
        self::KIND_TEMPLATE => 'قالب آماده (صفحه، بخش، هدر، فوتر)',
        self::KIND_WIDGET => 'ویجت',
        self::KIND_MOTION => 'موشن گرافیک (تکی)',
        self::KIND_HEADER => 'قالب هدر (تکی و دسته‌ای)',
        self::KIND_BUTTON_PACK => 'کتابخانه دکمه‌ها (دسته‌ای/پکیجی)',
        self::KIND_ICON_PACK => 'کتابخانه آیکن‌های اختصاصی (دسته‌ای/پکیجی)',
    ];

    /**
     * Determines whether the given asset kind represents a grouped/packaged library
     * (such as button libraries or icon libraries sold/licensed in batches).
     */
    public static function isPackKind(string $kind): bool
    {
        return in_array($kind, [self::KIND_BUTTON_PACK, self::KIND_ICON_PACK], true);
    }

    /**
     * Determines whether the given asset kind is managed or licensed individually.
     */
    public static function isIndividualKind(string $kind): bool
    {
        return in_array($kind, [self::KIND_MOTION, self::KIND_HEADER, self::KIND_TEMPLATE, self::KIND_WIDGET], true);
    }

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
