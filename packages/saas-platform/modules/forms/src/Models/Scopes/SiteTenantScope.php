<?php

namespace SaaSPlatform\Modules\Forms\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * PHASE 8.0 — Structural tenant isolation for form models with a site_id column.
 * Mirrors the tickets module SiteTenantScope — both resolve from the same
 * tenant_site request attribute set by AuthenticateTenantApi.
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
