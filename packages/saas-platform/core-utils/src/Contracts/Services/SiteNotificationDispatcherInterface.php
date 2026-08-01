<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Phase 20 — Part 6/7: a generic (not CRM-specific) extension point, same
 * pattern as SeoOverrideProviderInterface/SitemapUrlProviderInterface
 * (Phase 18-B) — a module that needs to notify a site's admin never
 * touches App\Models\* directly; it calls this interface, and the host
 * app supplies the real implementation (wrapping whatever in-app
 * notification table / SMS gateway Core already owns — see
 * App\Services\CoreSiteNotificationDispatcher).
 *
 * If nothing binds a real implementation (e.g. a module used standalone
 * without the host's AppServiceProvider), callers MUST check
 * app()->bound(self::class) first — there is no default binding here,
 * unlike SeoOverrideProviderInterface's Null Object default, since
 * "silently drop a reminder" is a worse failure mode than "the caller
 * explicitly decided not to send."
 */
interface SiteNotificationDispatcherInterface
{
    public function notifySiteAdmin(int $siteId, string $title, string $message): void;
}
