<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Module extends Model
{
    public const TYPE_CORE = 'core';
    public const TYPE_PAID = 'paid';
    public const TYPE_INTERNAL = 'internal';

    /** Distribution modes for tenant assignment */
    public const MODE_FREE_ASSIGNABLE = 'free_assignable';
    public const MODE_PAID_MARKETPLACE = 'paid_marketplace';
    public const MODE_LICENSE_ONLY = 'license_only';
    public const MODE_DISABLED = 'disabled';
    public const MODE_RETIRED = 'retired';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'is_active',
        'free_assignable',
        'license_required',
        'marketplace_visible',
        'category',
        'version',
        'type',
        'price_irr',
        'price_promo_irr',
        'currency',
        'license_type_default',
        'short_description',
        'screenshots',
        'retired_at',
    ];

    protected $casts = [
        'price_irr' => 'integer',
        'price_promo_irr' => 'integer',
        'is_active' => 'boolean',
        'free_assignable' => 'boolean',
        'license_required' => 'boolean',
        'marketplace_visible' => 'boolean',
        'screenshots' => 'array',
        'retired_at' => 'datetime',
    ];

    /** نسخهٔ پیش‌فرض برای ماژول‌های جدید */
    public const DEFAULT_VERSION = '1.0.0';

    /**
     * ماژول‌های قابل اختصاص به سایت (core و paid؛ internal هرگز).
     * ماژول‌های بازنشسته (retired) از این لیست حذف می‌شوند.
     */
    public function scopeAssignableToSite(Builder $query): Builder
    {
        $query->whereIn('type', [self::TYPE_CORE, self::TYPE_PAID]);
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'retired_at')) {
            $query->whereNull('retired_at');
        }
        return $query;
    }

    /**
     * ماژول‌های فعال و بازنشسته نشده (پیشنهاد می‌شود در فهرست‌های assignment/marketplace استفاده شود).
     */
    public function scopeNotRetired(Builder $query): Builder
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'retired_at')) {
            return $query->whereNull('retired_at');
        }
        return $query;
    }

    /**
     * فقط ماژول‌های بازنشسته.
     */
    public function scopeRetired(Builder $query): Builder
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'retired_at')) {
            return $query->whereNotNull('retired_at');
        }
        return $query->whereRaw('0=1');
    }

    /**
     * ماژول‌های قابل اختصاص دستی توسط ادمین (فقط Free Assignable).
     * این ماژول‌ها در صفحه «ماژول‌های سایت» نمایش داده می‌شوند.
     */
    public function scopeFreeAssignable(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'free_assignable')) {
            $query->where('free_assignable', true);
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'retired_at')) {
                $query->whereNull('retired_at');
            }
            return $query;
        }
        return $query->whereRaw('0=1');
    }

    /**
     * ماژول‌های قابل خرید در فروشگاه tenant (قیمت دارند، فقط از طریق خرید).
     * در پنل اصلی در لیست اختصاص دستی نمایش داده نمی‌شوند.
     * ماژول‌های بازنشسته از مارکت‌پلیس حذف می‌شوند ولی مالکیت قبلی مشتری حفظ می‌شود.
     */
    public function scopePaidMarketplace(Builder $query): Builder
    {
        $query->where('is_active', true)
            ->where('type', self::TYPE_PAID)
            ->where(function ($q) {
                $q->whereNotNull('price_irr')->where('price_irr', '>', 0);
            });
        $table = $query->getModel()->getTable();
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'license_required')) {
            $query->where('license_required', false);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'marketplace_visible')) {
            $query->where('marketplace_visible', true);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'retired_at')) {
            $query->whereNull('retired_at');
        }
        return $query;
    }

    /**
     * ماژول‌های فقط با لایسنس (غیرقابل خرید در مارکت؛ ادمین لایسنس صادر می‌کند).
     */
    public function scopeLicenseOnly(Builder $query): Builder
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'license_required')) {
            return $query->where('license_required', true);
        }
        return $query->whereRaw('0=1');
    }

    /**
     * ماژول‌های قابل نمایش در فروشگاه tenant (قیمت‌دار و بدون الزام لایسنس دستی).
     */
    public function scopeMarketplaceVisible(Builder $query): Builder
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'marketplace_visible')) {
            return $query->where('marketplace_visible', true);
        }
        return $query;
    }

    /**
     * ماژول‌های ارسالی به customer-site (فقط core و paid).
     */
    public function scopeCustomerFacing(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_CORE, self::TYPE_PAID]);
    }

    /**
     * وضعیت توزیع ماژول برای نمایش و قوانین اختصاص.
     *
     * توجه: حالت RETIRED صرفاً برای جلوگیری از **اختصاص جدید** است.
     * مشتریانی که قبلاً این ماژول را داشتند، entitlement خود را حفظ می‌کنند
     * (در `site_modules` و `site_module_purchases` و `module_licenses` دست‌نخورده باقی می‌ماند).
     */
    public function getDistributionMode(): string
    {
        if ($this->isRetired()) {
            return self::MODE_RETIRED;
        }
        if (! $this->is_active) {
            return self::MODE_DISABLED;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'license_required') && $this->license_required) {
            return self::MODE_LICENSE_ONLY;
        }
        $hasPrice = (int) ($this->price_irr ?? 0) > 0;
        if ($hasPrice) {
            return self::MODE_PAID_MARKETPLACE;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'free_assignable') && $this->free_assignable) {
            return self::MODE_FREE_ASSIGNABLE;
        }
        return self::MODE_DISABLED;
    }

    public function isFreeAssignable(): bool
    {
        return $this->getDistributionMode() === self::MODE_FREE_ASSIGNABLE;
    }

    public function isPaidMarketplace(): bool
    {
        return $this->getDistributionMode() === self::MODE_PAID_MARKETPLACE;
    }

    public function isLicenseOnly(): bool
    {
        return $this->getDistributionMode() === self::MODE_LICENSE_ONLY;
    }

    /**
     * A kit a site can be licensed for: a paid, license-only module, which is
     * exactly what ModuleLicenseGrantService::grant() accepts. Core platform
     * modules can also carry license_required (53 on production, 2026-11-17:
     * captcha, blog, marketing_automation, …), which makes them license-only
     * as well, but no path licenses a core module. Offering them as kits made
     * installation 53 fail APPLY_SITE_ENTITLEMENTS on «marketing_automation».
     */
    public function isSiteLicensableKit(): bool
    {
        return $this->type === self::TYPE_PAID && $this->isLicenseOnly();
    }

    public function isDistributionDisabled(): bool
    {
        return $this->getDistributionMode() === self::MODE_DISABLED;
    }

    /**
     * آیا ماژول بازنشسته شده است؟
     * ماژول بازنشسته در فهرست‌های assignment و marketplace ظاهر نمی‌شود،
     * ولی entitlementهای موجود مشتریان حفظ می‌شوند.
     */
    public function isRetired(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'retired_at')) {
            return false;
        }
        return $this->retired_at !== null;
    }

    /**
     * بازنشسته‌سازی ماژول (soft retirement). entitlementهای موجود حفظ می‌شوند؛
     * فقط از فهرست‌های assignment و marketplace کنار می‌رود.
     */
    public function retire(?\Illuminate\Support\Carbon $at = null): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'retired_at')) {
            return false;
        }
        $this->retired_at = $at ?? now();
        return $this->save();
    }

    /**
     * بازگردانی ماژول از حالت بازنشسته.
     */
    public function unretire(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'retired_at')) {
            return false;
        }
        $this->retired_at = null;
        return $this->save();
    }

    /**
     * Wave 5.1 / P1-1 — append-only ledger of every grant/revoke event
     * touching this module across all customer sites.
     */
    public function entitlements()
    {
        return $this->hasMany(\App\Models\ModuleEntitlement::class, 'module_id');
    }

    public function activeEntitlements()
    {
        return $this->entitlements()
            ->where('status', \App\Models\ModuleEntitlement::STATUS_ACTIVE);
    }

    /**
     * قیمت نمایشی (ترجیح پرومو در صورت وجود).
     */
    public function getDisplayPriceAttribute(): ?int
    {
        $promo = $this->getAttribute('price_promo_irr');
        $price = $this->getAttribute('price_irr');
        if ($promo !== null && (int) $promo > 0) {
            return (int) $promo;
        }
        return $price !== null ? (int) $price : null;
    }

    /**
     * آیا ماژول رایگان است؟
     */
    public function isFree(): bool
    {
        return (int) ($this->price_irr ?? 0) === 0 && (int) ($this->price_promo_irr ?? 0) === 0;
    }

    public function isInternal(): bool
    {
        return $this->type === self::TYPE_INTERNAL;
    }

    public function isCore(): bool
    {
        return $this->type === self::TYPE_CORE;
    }

    public function isPaid(): bool
    {
        return $this->type === self::TYPE_PAID;
    }

    /**
     * آیا نسخهٔ نصب‌شده قدیمی‌تر از نسخهٔ فعلی ماژول است؟
     *
     * @deprecated Commit 12-I audit — compares $this->version (modules.version,
     *             a denormalized cache), not module_releases, the version
     *             authority (see docs/architecture/module-version-authority-adr.md).
     *             Has no call sites as of Commit 12-J; kept for backward
     *             compatibility only. Use ModuleUpdateEligibilityService
     *             (backed by ModuleRelease::latestForModule()) for any new
     *             update-availability decision instead of this method.
     */
    public function hasUpdateAvailable(?string $installedVersion): bool
    {
        if ($installedVersion === null || $installedVersion === '') {
            return true;
        }
        return version_compare((string) $this->version, $installedVersion, '>');
    }

    public function features()
    {
        return $this->hasMany(ModuleFeature::class);
    }

    public function releases()
    {
        return $this->hasMany(ModuleRelease::class);
    }
}
