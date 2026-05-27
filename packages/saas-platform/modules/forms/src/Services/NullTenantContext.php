<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Null tenant context for single-tenant installations (e.g. customer-site)
 * that have no multi-tenant TenantContextContract binding.
 *
 * Returns safe defaults: no tenant, no reseller/site, all features disabled.
 * PlatformContextService will fall back to CONTEXT_TENANT via platformHasMultiSite().
 */
class NullTenantContext implements TenantContextContract
{
    public function current(): ?object     { return null; }
    public function type(): ?string        { return null; }
    public function isReseller(): bool     { return false; }
    public function isSite(): bool         { return false; }
    public function reseller(): ?object    { return null; }
    public function site(): ?object        { return null; }
    public function id(): ?int             { return null; }
    public function hasTenant(): bool      { return false; }
    public function hasFeature(string $feature): bool { return false; }
}
