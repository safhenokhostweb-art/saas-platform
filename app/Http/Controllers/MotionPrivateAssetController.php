<?php

namespace App\Http\Controllers;

use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use App\Modules\MotionGraphics\Support\MotionPrivateAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * ADR-081: serves an exclusive (customer_specific) library motion at its
 * site-scoped signed URL (and, ADR-086, a private catalog motion to a site
 * holding an active grant for it),
 *
 *   /motion/private/{slug}/{vN}/{site}/{expires}/{signature}/animation.svg
 *
 * which only the `tenant.api` runtime manifest of the owning site hands out
 * (MotionPrivateAssetUrl). The browser of a visitor has no license key, so
 * the signature is the credential: it names one site, one asset version and
 * an expiry, and cannot be forged or re-scoped.
 *
 * Every request also re-checks the catalog, so revocation is immediate
 * rather than waiting for expiry:
 * - the motion is still published, customer_specific, and owned by the
 *   signed site, and its files still declare that owner;
 * - if the site's access is managed by a bespoke assignment, one of them is
 *   still active.
 *
 * Every refusal is a 404, so an outsider cannot tell whether an exclusive
 * motion exists. Runtime parameters in the query string are applied exactly
 * as on the public route. Caching is `private`: shared caches never keep an
 * exclusive file, and no response is marked immutable.
 */
final class MotionPrivateAssetController
{
    public function __invoke(
        Request $request,
        MotionPrivateAssetUrl $urls,
        string $slug,
        string $version,
        string $site,
        string $expires,
        string $signature,
    ): Response {
        abort_unless(
            preg_match('/^v[1-9][0-9]{0,3}$/', $version) === 1
                && (MotionLibraryCatalogService::isExclusive($slug) || MotionLibraryCatalogService::isPrivateLibraryFile($slug, $version))
                && ctype_digit($site) && ctype_digit($expires)
                && preg_match('/^[a-f0-9]{64}$/', $signature) === 1,
            404
        );
        abort_unless($urls->verify($slug, $version, (int) $site, (int) $expires, $signature), 404);
        abort_unless($this->siteMayRead($slug, $version, (int) $site), 404);
        abort_if(strlen((string) $request->server('QUERY_STRING', '')) > MotionLibraryAssetController::MAX_QUERY_BYTES, 414);

        $svg = MotionLibraryCatalogService::render($slug, $version, $request->query());
        abort_if($svg === null, 404);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }

    private function siteMayRead(string $slug, string $version, int $siteId): bool
    {
        $motion = MotionGraphic::query()->where('slug', $slug)->first();
        if ($motion === null || $motion->published_version === null) {
            return false;
        }

        // ADR-086: a private global motion is readable by a site only while
        // it holds an active grant, re-checked here on every request, so a
        // revoked grant stops the file at once, not when the URL expires.
        if ($motion->ownership_type === MotionGraphic::OWNERSHIP_GLOBAL) {
            return $motion->visibility() === MotionGraphic::VISIBILITY_PRIVATE
                && MotionLibraryCatalogService::isPrivateLibraryFile($slug, $version)
                && MotionSiteAssetGrant::isGranted($siteId, (string) $motion->uuid);
        }

        if ($motion->ownership_type !== MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
            || $motion->owner_site_identifier !== (string) $siteId
            || MotionLibraryCatalogService::fileOwnerSiteId($slug, $version) !== $siteId) {
            return false;
        }

        $assignments = MotionBespokeAssignment::query()
            ->where('asset_id', $motion->uuid)
            ->where('site_id', $siteId);

        return ! (clone $assignments)->exists()
            || $assignments->where('status', MotionBespokeAssignment::STATUS_ACTIVE)->exists();
    }
}
