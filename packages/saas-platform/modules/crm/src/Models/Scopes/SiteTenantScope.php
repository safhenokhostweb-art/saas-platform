<?php

namespace SaaSPlatform\Modules\Crm\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * Phase 19 — Part 3: structural tenant isolation for CRM models, copied
 * from the Tickets module's own SiteTenantScope (packages/saas-platform/
 * modules/tickets/src/Models/Scopes/SiteTenantScope.php) — same
 * mechanism, same host middleware dependency (AuthenticateTenantApi sets
 * `tenant_site` on the request).
 *
 * When a `tenant_site` object is present on the current request, every
 * query is automatically constrained to that site's data. In admin/web
 * contexts where no tenant_site is set, this is a no-op so admins retain
 * full cross-site visibility.
 *
 * Bypass (admin-only): Model::withoutTenantIsolation()->get()
 */
class SiteTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $siteId = $this->resolveCurrentSiteId();
        if ($siteId === null) {
            return;
        }

        $table = $model->getTable();
        $builder->where("{$table}.site_id", $siteId);
    }

    private function resolveCurrentSiteId(): ?int
    {
        return app(SiteContextContract::class)->siteId();
    }
}
