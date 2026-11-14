<?php

namespace App\Http\Controllers;

use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * ADR-078/079: serves a curated motion library file at the root-relative URL
 * the runtime manifest carries (`/motion/{slug}/{vN}/animation.svg`, the
 * module's `asset_path_prefix`). The library directory is the only copy.
 *
 * Query parameters are the asset version's declared runtime parameters
 * (texts, colour tokens, animate/loop/speed). They are validated and
 * applied by MotionSvgComposer: unknown or invalid values are ignored, text
 * is set as DOM text (never markup), and Vazirmatn is embedded so `<img>`
 * rendering never falls back to a system font. The same URL always yields
 * the same bytes, hence the immutable caching.
 *
 * Only catalog slugs and existing `vN` directories resolve; everything else
 * is a 404. Every version stays reachable, because a retired catalog version
 * may still be referenced by a manifest a customer site has cached.
 *
 * Registered outside the `web` group (no session, no cookies). The response
 * carries its own hardening: no sniffing, and a CSP that allows only the
 * SVG's inline styles and its embedded data: font.
 */
final class MotionLibraryAssetController
{
    public const MAX_QUERY_BYTES = 4096;

    public function __invoke(Request $request, string $slug, string $version): Response
    {
        abort_unless(
            array_key_exists($slug, MotionLibraryCatalogService::ASSETS) && preg_match('/^v[1-9][0-9]{0,3}$/', $version) === 1,
            404
        );
        // ADR-081: a file that declares an owner is never public, even if its
        // slug were ever listed here; it is served by MotionPrivateAssetController.
        abort_if(MotionLibraryCatalogService::fileOwnerSiteId($slug, $version) !== null, 404);
        // ADR-086: likewise a private (grant-only) file; only signed URLs of
        // a granted site reach it.
        abort_if(MotionLibraryCatalogService::fileVisibility($slug, $version) !== MotionGraphic::VISIBILITY_PUBLIC, 404);
        abort_if(strlen((string) $request->server('QUERY_STRING', '')) > self::MAX_QUERY_BYTES, 414);

        $svg = MotionLibraryCatalogService::render($slug, $version, $request->query());
        abort_if($svg === null, 404);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
