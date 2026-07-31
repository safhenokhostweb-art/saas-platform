<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Phase 18-B — the generic (not SEO-Advanced-specific) extension point a
 * tenant runtime's native SEO rendering consults before falling back to
 * its own defaults. Any module may bind an implementation; the host app
 * (customer-site) binds a Null Object default (see
 * App\Services\Seo\NullSeoOverrideProvider) so nothing here ever requires
 * the host to know about, or depend on, a specific SEO module.
 */
interface SeoOverrideProviderInterface
{
    /**
     * @return array{title?: ?string, description?: ?string, robots?: ?string, canonical?: ?string, ogImage?: ?string, twitterCard?: ?string}|null
     *         null (or a missing key) means "no override — use the host's
     *         existing default for that field", never "force empty".
     */
    public function overrideFor(): ?array;

    /**
     * Additional JSON-LD structures to render alongside whatever the host
     * already renders natively (Organization/WebSite/etc.) — never a
     * replacement for them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extraJsonLd(): array;
}
