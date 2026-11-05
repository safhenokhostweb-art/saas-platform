<?php

namespace App\Http\Controllers;

use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ADR-078: serves a curated motion library file at the root-relative URL the
 * runtime manifest carries (`/motion/{slug}/{vN}/animation.svg`, the module's
 * `asset_path_prefix`). The library directory is the only copy — nothing is
 * duplicated into public/.
 *
 * Only catalog slugs and existing `vN` directories resolve; everything else
 * is a 404. Every version stays reachable, because a retired catalog version
 * may still be referenced by a manifest a customer site has cached.
 *
 * Registered outside the `web` group (no session, no cookies). The global
 * SecurityHeaders middleware skips binary responses, so this response sets
 * its own hardening: no sniffing, and a CSP that lets the SVG use only its
 * own inline styles if it is ever opened directly.
 */
final class MotionLibraryAssetController
{
    public function __invoke(string $slug, string $version): BinaryFileResponse
    {
        abort_unless(
            array_key_exists($slug, MotionLibraryCatalogService::ASSETS) && preg_match('/^v[1-9][0-9]{0,3}$/', $version) === 1,
            404
        );

        $path = MotionLibraryCatalogService::libraryDirectory($slug, $version).'/animation.svg';
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
