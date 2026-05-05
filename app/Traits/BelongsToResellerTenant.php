<?php

namespace App\Traits;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * برای مدل‌هایی که به Reseller (tenant) وابسته‌اند.
 * با فراخوانی scopeForCurrentTenant() کوئری به tenant فعلی محدود می‌شود.
 */
trait BelongsToResellerTenant
{
    /**
     * محدود کردن کوئری به tenant (Reseller) فعلی.
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantContext = app(TenantContext::class);
        $reseller = $tenantContext->reseller();

        if (! $reseller) {
            return $query;
        }

        $column = $this->getTenantColumn();

        return $query->where($column, $reseller->id);
    }

    /**
     * نام ستون tenant (پیش‌فرض: reseller_id).
     */
    protected function getTenantColumn(): string
    {
        return $this->tenantColumn ?? 'reseller_id';
    }

    /**
     * آیا این رکورد به tenant فعلی تعلق دارد؟
     */
    public function belongsToCurrentTenant(): bool
    {
        $tenantContext = app(TenantContext::class);
        $reseller = $tenantContext->reseller();

        if (! $reseller) {
            return false;
        }

        $column = $this->getTenantColumn();

        return isset($this->{$column}) && (int) $this->{$column} === (int) $reseller->id;
    }
}
