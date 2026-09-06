<?php

namespace App\Services\ThemeBuilder\Extension;

use App\Models\Theme;

/**
 * The ONE place a Theme's selected Extension releases' manifest-declared
 * `assets` (root-relative, saas-platform-local paths — see
 * ExtensionElementDefinition's own doc comment) are turned into the
 * ABSOLUTE URLs a completely different application (customer-site) can
 * embed into its own rendered HTML.
 *
 * Reuses ExtensionCanvasPaletteAdapter::elementAssetsFor() (the existing,
 * already-tested manifest-read authority) rather than re-deriving the same
 * "selected release -> declared element assets" projection a second time —
 * this class's only job is the one thing that authority deliberately does
 * NOT do: resolve a declared path into a URL a foreign origin can load.
 *
 * Follows the exact precedent already established for Theme-authored media
 * (ThemeMediaController::upload() / config/filesystems.php's
 * `'public' => ['url' => env('APP_URL').'/storage']`): a saas-platform
 * -authored static asset that must render on customer-site's domain is
 * shipped as an absolute URL rooted at THIS app's own APP_URL, never copied
 * or synced — there is no existing cross-app file-sync/shared-storage/CDN
 * mechanism to hook into instead (confirmed by audit: image_url props are
 * hotlinked absolute URLs the same way). Extension release assets are
 * immutable and version-namespaced (ExtensionManifest::
 * assertAssetsNamespacedUnderRelease()), so this absolute URL is stable for
 * the lifetime of that release exactly like every other reference to it.
 */
final class ExtensionThemeRuntimeAssetProjector
{
    /**
     * @return array<string, array{css: string[], js: string[]}> ext:* type => absolute-URL {css, js},
     *                                                           only for types that actually declare at least one asset. Empty when
     *                                                           the Theme selects no Extension, or none of its selected releases
     *                                                           declare any asset — cheap, harmless no-op for every pre-existing
     *                                                           Theme (backward compatible by construction).
     */
    public static function project(Theme $theme): array
    {
        $elementAssets = ExtensionCanvasPaletteAdapter::elementAssetsFor($theme);

        $projected = [];
        foreach ($elementAssets as $type => $assets) {
            $css = array_map(self::toAbsoluteUrl(...), $assets['css']);
            $js = array_map(self::toAbsoluteUrl(...), $assets['js']);

            if ($css === [] && $js === []) {
                continue;
            }

            $projected[$type] = ['css' => $css, 'js' => $js];
        }

        return $projected;
    }

    private static function toAbsoluteUrl(string $declaredPath): string
    {
        return rtrim((string) config('app.url'), '/').$declaredPath;
    }
}
