<?php
namespace App\Services;

use App\Models\Reseller;
use App\Models\Site;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Traits\HasTenantContext;

/**
 * سرویس مرکزی برای دسترسی به tenant فعلی.
 * Tenant از دامنه/زیردامنه یا از route/request استخراج می‌شود.
 *
 * این کلاس اینترفیس TenantContextContract را پیاده‌سازی می‌کند
 * و از trait HasTenantContext برای متد hasFeature() استفاده می‌کند.
 */
class TenantContext implements TenantContextContract
{
    use HasTenantContext;

    protected ?object $tenant = null;

    protected ?string $tenantType = null;

    public function __construct(
        protected Application $app,
        protected TenantResolver $resolver
    ) {}

    /**
     * tenant فعلی را برگردان (Reseller یا Site).
     */
    public function current(): ?object
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        $request = $this->app->make(Request::class);
        if (! $request) {
            return null;
        }

        // ۱. از domain (Reseller)
        $fromDomain = $this->resolver->resolveFromRequest($request);
        if ($fromDomain) {
            $this->set($fromDomain);
            return $this->tenant;
        }

        // ۲. از request attributes (مثلاً EnsureUserIsReseller)
        if ($request->attributes->has('current_reseller')) {
            $reseller = $request->attributes->get('current_reseller');
            if ($reseller instanceof Reseller) {
                $this->set($reseller);
                return $this->tenant;
            }
        }

        // ۳. از domain برای branding (current_reseller_from_domain)
        if ($request->attributes->has('current_reseller_from_domain')) {
            $reseller = $request->attributes->get('current_reseller_from_domain');
            if ($reseller instanceof Reseller) {
                $this->set($reseller);
                return $this->tenant;
            }
        }

        return null;
    }

    /**
     * tenant را صریحاً تنظیم کن.
     */
    public function set(object $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenantType = $this->resolver->getTenantType($tenant);
    }

    /**
     * context را پاک کن (مثلاً بین درخواست‌ها).
     */
    public function clear(): void
    {
        $this->tenant = null;
        $this->tenantType = null;
    }

    /**
     * نوع tenant فعلی.
     */
    public function type(): ?string
    {
        if ($this->tenantType !== null) {
            return $this->tenantType;
        }
        $this->current();
        return $this->tenantType;
    }

    /**
     * آیا tenant فعلی Reseller است؟
     */
    public function isReseller(): bool
    {
        return $this->type() === 'reseller';
    }

    /**
     * آیا tenant فعلی Site است؟
     */
    public function isSite(): bool
    {
        return $this->type() === 'site';
    }

    /**
     * Reseller فعلی (در صورت وجود).
     */
    public function reseller(): ?Reseller
    {
        $t = $this->current();
        return $t instanceof Reseller ? $t : null;
    }

    /**
     * Site فعلی (در صورت وجود).
     */
    public function site(): ?Site
    {
        $t = $this->current();
        return $t instanceof Site ? $t : null;
    }

    /**
     * شناسهٔ tenant برای scope کردن کوئری‌ها.
     */
    public function id(): ?int
    {
        $t = $this->current();
        return $t && isset($t->id) ? (int) $t->id : null;
    }

    /**
     * کوئری را به tenant فعلی (Reseller) محدود کن.
     * برای مدل‌هایی که reseller_id دارند.
     */
    public function scopeQueryToTenant($query, string $tenantColumn = 'reseller_id')
    {
        $reseller = $this->reseller();
        if ($reseller && $query) {
            return $query->where($tenantColumn, $reseller->id);
        }
        return $query;
    }

    /**
     * آیا در context یک tenant هستیم؟
     */
    public function hasTenant(): bool
    {
        return $this->current() !== null;
    }
}
