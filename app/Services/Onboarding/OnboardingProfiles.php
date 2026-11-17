<?php

namespace App\Services\Onboarding;

/**
 * ADR-088 — static onboarding data, versioned with the code (not a config
 * file: `config/` is outside the release policy's allowed paths, and none of
 * this varies per environment).
 *
 * Industry profiles map a business vertical (BusinessVerticals) to the
 * canonical theme the wizard pre-selects for it; that theme's pages — its
 * home page included (e.g. the solar-industry theme's `solar-page-home`) —
 * are bound to the site once its installation completes. The kits a
 * vertical needs are NOT listed here: they are derived from the
 * theme-runtime access policy families (`required_vertical`,
 * `entitlement_keys`), so this class never restates the licensing rules.
 */
final class OnboardingProfiles
{
    /** vertical => canonical theme slug */
    public const INDUSTRY_THEMES = [
        'solar_energy' => 'solar-industry',
    ];

    /** Labels a platform subdomain may never take: they are platform hosts. */
    public const RESERVED_SUBDOMAINS = [
        'www', 'panel', 'admin', 'api', 'app', 'mail', 'smtp', 'imap', 'pop',
        'ftp', 'ns1', 'ns2', 'cdn', 'static', 'assets', 'deployment', 'hub',
        'dashboard', 'status', 'support', 'billing', 'staging', 'test',
    ];

    public static function themeSlugFor(string $vertical): ?string
    {
        return self::INDUSTRY_THEMES[$vertical] ?? null;
    }

    public static function isReservedSubdomain(string $label): bool
    {
        return in_array($label, self::RESERVED_SUBDOMAINS, true);
    }
}
