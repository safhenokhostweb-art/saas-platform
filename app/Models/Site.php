<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سایتِ زیرمجموعه (مثل customer-site).
 * همهٔ مدیریت‌ها (ماژول، تنظیمات و …) از پنل saas-platform انجام می‌شود.
 */
class Site extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'domain',
        'hosting_company',
        'db_name',
        'db_user',
        'db_password',
        'db_host',
        'support_expires_at',
        'host_expires_at',
        'domain_expires_at',
        'support_starts_at',
        'support_level',
        'is_active',
        'customer_menu_keys',
        'payment_gateway_id',
        'theme_id',
    ];

    protected $casts = [
        'support_expires_at' => 'date',
        'host_expires_at' => 'date',
        'domain_expires_at' => 'date',
        'support_starts_at' => 'date',
        'is_active' => 'boolean',
        'customer_menu_keys' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function modules()
    {
        return $this->belongsToMany(\App\Models\Module::class, 'site_modules')
            ->withPivot(['is_active', 'installed_version', 'version_reported_at', 'activated_at'])
            ->withTimestamps();
    }

    /**
     * شناسهٔ فیچرهایی که برای این سایت (از طریق site_modules و site_module_features) فعال شده‌اند.
     *
     * @return array<int, int>
     */
    public function getEnabledModuleFeatureIds(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('site_module_features') || ! \Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            return [];
        }
        return \Illuminate\Support\Facades\DB::table('site_module_features')
            ->join('site_modules', 'site_module_features.site_module_id', '=', 'site_modules.id')
            ->where('site_modules.site_id', $this->id)
            ->where('site_module_features.is_active', true)
            ->pluck('site_module_features.module_feature_id')
            ->unique()
            ->values()
            ->all();
    }

    public function getTotalPriceAttribute()
    {
        return $this->modules->sum('price');
    }

    public function menus()
    {
        return $this->hasMany(\App\Models\Menu::class);
    }

    public function seoRedirects()
    {
        return $this->hasMany(\App\Models\SeoRedirect::class);
    }

    public function seoPageScores()
    {
        return $this->hasMany(\App\Models\SeoPageScore::class);
    }

    public function forms()
    {
        return $this->belongsToMany(\App\Models\Form::class, 'form_site')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function formSubmissions()
    {
        return $this->hasMany(\App\Models\FormSubmission::class);
    }

    public function captchaSetting()
    {
        return $this->hasOne(\App\Models\SiteCaptchaSetting::class);
    }

    public function siteUpdates()
    {
        return $this->hasMany(\App\Models\SiteUpdate::class)->orderByDesc('distributed_at');
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function platformLicenses()
    {
        return $this->hasMany(\App\Models\PlatformLicense::class, 'site_id');
    }

    public function modulePurchases()
    {
        return $this->hasMany(\App\Models\ModulePurchase::class, 'site_id');
    }

    public function moduleLicenses()
    {
        return $this->hasMany(\App\Models\ModuleLicense::class, 'site_id');
    }

    /**
     * Wave 5.1 / P1-1 — append-only ledger of every grant/revoke event for
     * this site. Independent from site_modules; used for audit, not for
     * run-time gating in Phase A.
     */
    public function entitlements()
    {
        return $this->hasMany(\App\Models\ModuleEntitlement::class, 'site_id');
    }

    public function activeEntitlements()
    {
        return $this->entitlements()
            ->where('status', \App\Models\ModuleEntitlement::STATUS_ACTIVE);
    }

    /** Support expiration: expired, expiring_soon (<= 30 days), valid */
    public function supportStatus(): string
    {
        if ($this->support_expires_at === null) {
            return 'valid';
        }
        if ($this->support_expires_at->isPast()) {
            return 'expired';
        }
        if ($this->support_expires_at->diffInDays(now()) <= 30) {
            return 'expiring_soon';
        }
        return 'valid';
    }

    public function isSupportExpired(): bool
    {
        return $this->supportStatus() === 'expired';
    }

    /**
     * آیا این سایت ماژول درگاه بانکی دارد؟
     */
    public function hasBankGatewayModule(): bool
    {
        return $this->modules()->where('slug', 'bank_gateway')->wherePivot('is_active', true)->exists();
    }

    /**
     * آیا ماژول با slug مشخص برای این سایت فعال است؟
     */
    public function hasModuleEnabled(string $slug): bool
    {
        return $this->modules()
            ->where('slug', $slug)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * slug ماژول‌های فعال (فقط core و paid).
     *
     * @return array<string>
     */
    public function getEnabledModuleSlugs(): array
    {
        return app(\App\Services\ModuleAssignmentService::class)->getEnabledSlugsForSite($this);
    }

    /**
     * آیا سایت ماژول PAID را خریده/دارای لایسنس معتبر دارد؟
     */
    public function hasPurchasedModule(string $moduleSlug): bool
    {
        return app(\App\Services\ModuleLicenseService::class)->hasValidLicenseBySlug($this, $moduleSlug);
    }

    /**
     * شناسهٔ ماژول‌های دارای لایسنس معتبر (PAID).
     *
     * @return array<int>
     */
    public function getPurchasedModuleIds(): array
    {
        return app(\App\Services\ModuleLicenseService::class)->getLicensedModuleIdsForSite($this);
    }
}
