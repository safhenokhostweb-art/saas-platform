<?php

namespace SaaSPlatform\Modules\Tickets\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * PHASE 8.0 — Structural tenant isolation for ticket models.
 *
 * When a `tenant_site` object is present on the current request (set by
 * AuthenticateTenantApi middleware), this scope automatically constrains
 * ALL queries to that site's data.
 *
 * In admin/web contexts where no tenant_site is set, the scope is a no-op
 * so admins retain full visibility.
 *
 * Bypass (admin-only):  Model::withoutTenantIsolation()->get()
 */
class SiteTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $site = $this->resolveCurrentSite();

        if ($site === null) {
            // No authenticated tenant context — allow query through unscoped.
            // Admin routes intentionally do not set tenant_site.
            return;
        }

        $table = $model->getTable();
        $builder->where("{$table}.site_id", $site->id);
    }

    /**
     * Resolve the currently authenticated tenant site from the request.
     * Returns null when running in admin, console, or queue contexts.
     */
    private function resolveCurrentSite(): ?object
    {
        if (!app()->bound('request')) {
            return null;
        }

        /** @var \Illuminate\Http\Request|null $request */
        $request = app('request');

        if (!$request || !method_exists($request, 'attributes')) {
            return null;
        }

        return $request->attributes->get('tenant_site');
    }
}
