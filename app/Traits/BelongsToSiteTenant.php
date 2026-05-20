<?php
namespace App\Traits;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;

/**
 * برای مدل‌هایی که به Site (tenant) وابسته‌اند.
 * با فراخوانی scopeForSite() کوئری به site مشخص محدود می‌شود.
 */
trait BelongsToSiteTenant
{
    /**
     * محدود کردن کوئری به site مشخص.
     */
    public function scopeForSite(Builder $query, Site|int $site): Builder
    {
        $siteId = $site instanceof Site ? $site->id : (int) $site;

        return $query->where($this->getSiteTenantColumn(), $siteId);
    }

    /**
     * نام ستون site (پیش‌فرض: site_id).
     */
    protected function getSiteTenantColumn(): string
    {
        return $this->siteTenantColumn ?? 'site_id';
    }
}
