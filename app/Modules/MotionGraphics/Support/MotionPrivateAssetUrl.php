<?php

namespace App\Modules\MotionGraphics\Support;

use Illuminate\Support\Carbon;

/**
 * ADR-081: site-scoped signed URLs for exclusive (customer_specific) library
 * motions.
 *
 * A published exclusive version stores an unsigned base reference,
 * `/motion/private/{slug}/{vN}/animation.svg`, which no route serves. The
 * runtime manifest (reached only through the `tenant.api` license channel)
 * turns it into
 *
 *   /motion/private/{slug}/{vN}/{site}/{expires}/{signature}/animation.svg
 *
 * for the requesting site. The signature is an HMAC of slug, version, site
 * and expiry under a key derived from APP_KEY, so a URL works for exactly one
 * site and one asset version, and cannot be forged or re-scoped.
 *
 * The signature lives in the path, never the query string: customer-site
 * appends the block's runtime parameters as a query string
 * (MotionRuntimeRenderer), and those must not invalidate the signature.
 *
 * Expiry is aligned to fixed windows, so every manifest built inside one
 * window carries the same URL. That keeps the manifest checksum and the
 * browser cache stable. A URL stays valid for one to two windows. Revocation
 * does not wait for expiry: MotionPrivateAssetController re-checks ownership
 * and any bespoke assignment on every request.
 */
final class MotionPrivateAssetUrl
{
    public const WINDOW_SECONDS = 7 * 24 * 3600;

    private const BASE_PATTERN = '#^/%s/private/([a-z0-9-]+)/(v[1-9][0-9]{0,3})/animation\.svg$#';

    public static function prefix(): string
    {
        return '/'.trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/').'/private/';
    }

    /** The unsigned reference a published exclusive version stores. */
    public static function baseReference(string $slug, string $libraryVersion): string
    {
        return self::prefix().$slug.'/'.$libraryVersion.'/animation.svg';
    }

    public static function isPrivateReference(?string $reference): bool
    {
        return is_string($reference) && str_starts_with($reference, self::prefix());
    }

    /**
     * Signs an unsigned base reference for one site. Returns null for
     * anything that is not a well-formed base reference, so a malformed
     * private reference is withheld, never passed through unsigned.
     */
    public function sign(string $baseReference, int $siteId): ?string
    {
        $prefix = trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/');
        if ($siteId < 1 || preg_match(sprintf(self::BASE_PATTERN, preg_quote($prefix, '#')), $baseReference, $m) !== 1) {
            return null;
        }
        [, $slug, $version] = $m;
        $expires = $this->expiresAt();

        return self::prefix().implode('/', [$slug, $version, $siteId, $expires, $this->signature($slug, $version, $siteId, $expires)]).'/animation.svg';
    }

    public function verify(string $slug, string $version, int $siteId, int $expires, string $signature): bool
    {
        if ($siteId < 1 || $expires < Carbon::now()->getTimestamp()) {
            return false;
        }

        return hash_equals($this->signature($slug, $version, $siteId, $expires), $signature);
    }

    /** End of the window after the current one. */
    public function expiresAt(): int
    {
        return (intdiv(Carbon::now()->getTimestamp(), self::WINDOW_SECONDS) + 2) * self::WINDOW_SECONDS;
    }

    private function signature(string $slug, string $version, int $siteId, int $expires): string
    {
        return hash_hmac('sha256', implode('|', ['motion-private-asset', $slug, $version, $siteId, $expires]), $this->key());
    }

    private function key(): string
    {
        return hash('sha256', 'motion-private-asset|'.(string) config('app.key'), true);
    }

    /** True when the slug is a reserved path segment of this scheme. */
    public static function isReservedSlug(string $slug): bool
    {
        return $slug === 'private';
    }
}
