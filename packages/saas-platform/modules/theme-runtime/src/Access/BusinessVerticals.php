<?php

namespace SaaSPlatform\ThemeRuntime\Access;

/**
 * ADR-087: the business-vertical vocabulary a site's `business_vertical`
 * and a rule's `required_vertical` share. A vertical-exclusive kit is
 * added here first (the catalog contract test rejects a family whose
 * vertical is not listed).
 */
final class BusinessVerticals
{
    public const GENERAL = 'general';

    public const SOLAR_ENERGY = 'solar_energy';

    public const LABELS = [
        self::GENERAL => 'عمومی',
        self::SOLAR_ENERGY => 'صنعت برق و انرژی خورشیدی',
        'retail' => 'فروشگاهی و خرده‌فروشی',
        'corporate' => 'شرکتی و خدماتی',
        'education' => 'آموزشی',
        'healthcare' => 'سلامت و پزشکی',
        'hospitality' => 'رستوران و گردشگری',
        'construction' => 'ساختمان و صنعت',
    ];

    public static function isKnown(mixed $vertical): bool
    {
        return is_string($vertical) && array_key_exists($vertical, self::LABELS);
    }

    public static function label(?string $vertical): ?string
    {
        return $vertical === null ? null : (self::LABELS[$vertical] ?? $vertical);
    }
}
