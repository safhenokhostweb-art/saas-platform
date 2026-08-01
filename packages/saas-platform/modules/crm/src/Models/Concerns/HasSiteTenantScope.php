<?php

namespace SaaSPlatform\Modules\Crm\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use SaaSPlatform\Modules\Crm\Models\Scopes\SiteTenantScope;

/**
 * Phase 19 — Part 3: shared by every CRM model with a site_id column —
 * avoids repeating the same booted()/withoutTenantIsolation() pair on
 * each of the 9+ tenant-scoped models (mirrors Tickets' Ticket.php,
 * factored into a trait since CRM has many more scoped models).
 */
trait HasSiteTenantScope
{
    protected static function bootHasSiteTenantScope(): void
    {
        static::addGlobalScope(new SiteTenantScope());
    }

    public static function withoutTenantIsolation(): Builder
    {
        return static::withoutGlobalScope(SiteTenantScope::class);
    }
}
