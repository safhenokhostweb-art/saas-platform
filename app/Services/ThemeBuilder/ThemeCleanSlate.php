<?php

namespace App\Services\ThemeBuilder;

use App\Support\DataPurge\PurgeTarget;
use App\Support\DataPurge\TablePurger;

/**
 * Clean-slate purge of every platform theme and the records hanging off it:
 * releases, licenses, assignments, marketplace rows, revisions, templates,
 * packs, bundles, and site navigation menus. Sites, tenants, users, theme
 * profiles (colour/token presets) and media files are not touched.
 *
 * The order follows the live foreign keys: sites.theme_id is detached first,
 * then the RESTRICT holders (assignments, licenses) go before theme_releases,
 * and theme_releases before themes.
 *
 * Ids are kept for themes and theme_releases: storage/app/public/theme-media/
 * {theme id}/ survives the purge, and customer sites record platform theme
 * and release ids, so a new theme must never reuse an old one's id.
 */
class ThemeCleanSlate
{
    public const KIND = 'platform-theme-clean-slate';

    public static function purger(): TablePurger
    {
        return new TablePurger(self::KIND, [
            PurgeTarget::detach('sites', 'theme_id'),
            PurgeTarget::table('theme_assignments'),
            PurgeTarget::table('theme_licenses'),
            PurgeTarget::table('theme_purchase_orders'),
            PurgeTarget::table('theme_quality_reports'),
            PurgeTarget::table('theme_release_dependencies'),
            PurgeTarget::table('theme_releases', resetIds: false),
            PurgeTarget::table('theme_marketplace'),
            PurgeTarget::table('theme_revisions'),
            PurgeTarget::table('themes', resetIds: false),
            PurgeTarget::table('theme_bundle_orders'),
            PurgeTarget::table('theme_bundles'),
            PurgeTarget::table('theme_templates'),
            PurgeTarget::table('theme_packs'),
            PurgeTarget::table('menu_items'),
            PurgeTarget::table('menus'),
        ]);
    }
}
