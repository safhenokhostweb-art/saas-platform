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
    ];

    protected $casts = [
        'price_irr' => 'integer',
        'price_promo_irr' => 'integer',
        'is_active' => 'boolean',
        'free_assignable' => 'boolean',
        'license_required' => 'boolean',
        'marketplace_visible' => 'boolean',
        'screenshots' => 'array',
    ];

    /** نسخهٔ پیش‌فرض برای ماژول‌های جدید */
    public const DEFAULT_VERSION = '1.0.0';

    /**
     * ماژول‌های قابل اختصاص به سایت (core و paid؛ internal هرگز).
     */
    public function scopeAssignableToSite(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_CORE, self::TYPE_PAID]);
    }

    /**
     * ماژول‌های قابل اختصاص دستی توسط ادمین (فقط Free Assignable).
     * این ماژول‌ها در صفحه «ماژول‌های سایت» نمایش داده می‌شوند.
     */
    public function scopeFreeAssignable(Builder $query): Builder
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'free_assignable')) {
            return $query->where('free_assignable', true);
        }
        return $query->whereRaw('0=1');
    }

    /**
     * ماژول‌های قابل خرید در فروشگاه tenant (قیمت دارند، فقط از طریق خرید).
     * در پنل اصلی در لیست اختصاص دستی نمایش داده نمی‌شوند.
     */
    public function scopePaidMarketplace(Builder $query): Builder
    {
        $query->where('is_active', true)
            ->where('type', self::TYPE_PAID)
            ->where(function ($q) {
                $q->whereNotNull('price_irr')->where('price_irr', '>', 0);
            });
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'license_required')) {
            $query->where('license_required', false);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'marketplace_visible')) {
            $query->where('marketplace_visible', true);
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
     */
    public function getDistributionMode(): string
    {
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

    public function isDistributionDisabled(): bool
    {
        return $this->getDistributionMode() === self::MODE_DISABLED;
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
