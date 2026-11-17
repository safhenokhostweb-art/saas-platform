<?php

namespace App\Services\ThemeCatalog;

use App\Models\Theme;
use Database\Seeders\ArianaMultiPlusThemeSeeder;
use Database\Seeders\SalesSupportThemeSeeder;
use Database\Seeders\SolarIndustryThemeSeeder;

/**
 * The one version-controlled list of master theme sources.
 *
 * `theme-catalog:rebuild` runs every source (a full rebuild, for dev, test
 * and CI databases). `themes:sync-registry` runs after every production
 * deploy and touches only the sources marked `auto_sync => true`, the
 * active registry.
 *
 * `auto_sync` is a product decision per source. It stays false for:
 *  - the legacy sources removed from production by the 2026-11-01
 *    clean-slate purge, which a deploy must never bring back;
 *  - any source that seeds several themes at once, because installing one
 *    of them would install all of them.
 * Adding a new theme to the platform means adding its seeder here with
 * `auto_sync => true`. The next deploy then installs it and gives it its first
 * commercial release through the Mandatory Publish Gate, with no manual
 * command.
 *
 * @phpstan-type Source array{label: string, run: array{0: string, 1: array<string, mixed>}, expected_slugs: list<string>, auto_sync: bool}
 */
final class ThemeRegistry
{
    /** @var list<Source> */
    public const SOURCES = [
        [
            'label' => 'marketplace:seed',
            'run' => ['marketplace:seed', []],
            'expected_slugs' => [
                'khabar-online', 'akademi-danesh', 'clinic-salamat', 'daftar-hoqooqi',
                'azhans-maskan', 'restaurant-lux', 'portfolio-khalaq', 'azhans-digital',
                'bazar-online', 'narmafzar-abri', 'landing-herfei', 'majale-online',
                'vakil-pro', 'salon-ziba', 'safar-lux', 'khodro-pro', 'sanat-molodi',
                'sakhteman-iran', 'mode-fa', 'digi-shop', 'mahsool-vije', 'enterprise-cloud',
            ],
            'auto_sync' => false,
        ],
        [
            'label' => 'nexus:seed',
            'run' => ['nexus:seed', []],
            'expected_slugs' => ['nexus-pro-corporate', 'nexus-pro-personal', 'nexus-pro-store'],
            'auto_sync' => false,
        ],
        [
            'label' => 'acool:seed',
            'run' => ['acool:seed', []],
            'expected_slugs' => ['acool-hvac-service'],
            'auto_sync' => false,
        ],
        [
            'label' => 'malltina:seed',
            'run' => ['malltina:seed', []],
            'expected_slugs' => ['malltina-international'],
            'auto_sync' => false,
        ],
        [
            'label' => 'db:seed ' . SalesSupportThemeSeeder::class,
            'run' => ['db:seed', ['--class' => SalesSupportThemeSeeder::class, '--force' => true]],
            'expected_slugs' => ['sales-support-pro'],
            'auto_sync' => false,
        ],
        [
            'label' => 'db:seed ' . ArianaMultiPlusThemeSeeder::class,
            'run' => ['db:seed', ['--class' => ArianaMultiPlusThemeSeeder::class, '--force' => true]],
            'expected_slugs' => ['ariana-multiplus'],
            'auto_sync' => false,
        ],
        // ADR-084: «قالب صنعت برق», assembled from the template library.
        [
            'label' => 'db:seed ' . SolarIndustryThemeSeeder::class,
            'run' => ['db:seed', ['--class' => SolarIndustryThemeSeeder::class, '--force' => true]],
            'expected_slugs' => [SolarIndustryThemeSeeder::SLUG],
            'auto_sync' => true,
        ],
    ];

    /**
     * Owner rule (2026-11-17): no master theme is listed in the tenant
     * marketplace by default. A newly installed theme stays out of it until
     * the admin opens «فروشگاه قالب ← Listing», ticks «نمایش در فروشگاه» and
     * sets its price (free or paid). Reason: solar-industry was seeded listed
     * at price 0, so every tenant could take it free (safhenokhost.ir did).
     *
     * Applied only to a theme that was just created, never to an existing
     * one, so a later admin listing decision is never undone. The onboarding
     * wizard and admin assignment do not depend on the listing.
     */
    public const LIST_IN_MARKETPLACE_ON_INSTALL = false;

    public static function applyInstallDefaults(Theme $theme): void
    {
        if ((bool) $theme->is_marketplace !== self::LIST_IN_MARKETPLACE_ON_INSTALL) {
            $theme->forceFill(['is_marketplace' => self::LIST_IN_MARKETPLACE_ON_INSTALL])->save();
        }
    }

    /** @return Source|null */
    public static function sourceFor(string $slug): ?array
    {
        foreach (self::SOURCES as $source) {
            if (in_array($slug, $source['expected_slugs'], true)) {
                return $source;
            }
        }

        return null;
    }

    /** @return list<string> slugs of the active registry, in declaration order */
    public static function autoSyncSlugs(): array
    {
        $slugs = [];
        foreach (self::SOURCES as $source) {
            if ($source['auto_sync'] && count($source['expected_slugs']) === 1) {
                $slugs[] = $source['expected_slugs'][0];
            }
        }

        return $slugs;
    }
}
