<?php

namespace SaaSPlatform\Modules\Forms\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * PHASE 8.0 — Structural tenant isolation for form models with a site_id column.
 * Mirrors the tickets module SiteTenantScope — both resolve from the same
 * tenant_site request attribute set by AuthenticateTenantApi.
 */
class SiteTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $site = $this->resolveCurrentSite();

        if ($site === null) {
            return;
        }

        $table = $model->getTable();
        $builder->where("{$table}.site_id", $site->id);
    }

    private function resolveCurrentSite(): ?object
    {
        if (!app()->bound('request')) {
            return null;
        }

        $request = app('request');

        if (!$request || !method_exists($request, 'attributes')) {
            return null;
        }

        return $request->attributes->get('tenant_site');
    }
}
