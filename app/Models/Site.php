<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;

/**
 * سایتِ زیرمجموعه (مثل customer-site).
 * همهٔ مدیریت‌ها (ماژول، تنظیمات و …) از پنل saas-platform انجام می‌شود.
 */
class Site extends Model implements SiteModelInterface
{
    public function getId(): int       { return (int) $this->id; }
    public function getDomain(): ?string { return $this->domain; }
    public function getTitle(): ?string  { return $this->title; }
    public function isActive(): bool     { return (bool) $this->is_active; }

    public function getNameAttribute(): ?string { return $this->title; }

    const LIFECYCLE_ACTIVE        = 'active';
    const LIFECYCLE_GRACE_PERIOD  = 'grace_period';
    const LIFECYCLE_SUSPENDED     = 'suspended';
    const LIFECYCLE_ARCHIVED      = 'archived';
    const LIFECYCLE_DELETED       = 'deleted';

    const LIFECYCLE_STATES = [
        self::LIFECYCLE_ACTIVE,
        self::LIFECYCLE_GRACE_PERIOD,
        self::LIFECYCLE_SUSPENDED,
        self::LIFECYCLE_ARCHIVED,
        self::LIFECYCLE_DELETED,
    ];

    protected $fillable = [
        'customer_id',
        'title',
        'domain',
        'hosting_company',
        'db_name',
        'db_user',
        'db_password_encrypted',
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
        'features',
        'lifecycle_status',
        'lifecycle_changed_at',
        'grace_period_started_at',
        'grace_period_ends_at',
        'grace_reminder_sent_at',
        'suspended_at',
        'suspended_reason',
        'archived_at',
        'archive_path',
        'restore_requested_at',
        'lifecycle_deleted_at',
        'license_key',
        // ADR-087: set only through SiteAssetEntitlementService::setVertical().
        'business_vertical',
    ];

    protected $casts = [
        'support_expires_at'      => 'date',
        'host_expires_at'         => 'date',
        'domain_expires_at'       => 'date',
        'support_starts_at'       => 'date',
        'is_active'               => 'boolean',
        'customer_menu_keys'      => 'array',
        'features'                => 'array',
        'lifecycle_changed_at'    => 'datetime',
        'grace_period_started_at' => 'datetime',
        'grace_period_ends_at'    => 'datetime',
        'grace_reminder_sent_at'  => 'datetime',
        'suspended_at'            => 'datetime',
        'archived_at'             => 'datetime',
        'restore_requested_at'    => 'datetime',
        'lifecycle_deleted_at'    => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function themeAssignments()
    {
        return $this->hasMany(\App\Models\ThemeAssignment::class);
    }

    /**
     * Phase 13-O12a — central read path for the site's remote database
     * password, encrypted via CredentialVault in db_password_encrypted.
     * Mirrors DnsProviderSetting::getDecryptedToken() (vault resolved
     * internally, not injected).
     *
     * Phase 13-O12e — the legacy plaintext db_password column (and its
     * fallback here) has been removed entirely: every site's credential now
     * lives exclusively in db_password_encrypted (backfilled via
     * `php artisan sites:migrate-db-passwords`, verified via `--verify`
     * before the column was dropped). Returns null — never throws — both
     * when no credential has ever been set and when decryption fails,
     * matching every consumer's existing `?? ''`/`?: ''` handling.
     */
    public function getDecryptedDbPassword(): ?string
    {
        if (empty($this->db_password_encrypted)) {
            return null;
        }

        try {
            $vault = app(\App\Services\CustomerInstallation\CredentialVault::class);
            return $vault->decrypt($this->db_password_encrypted);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Site: Failed to decrypt db_password_encrypted', [
                'site_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function modules()
    {
        return $this->belongsToMany(\App\Models\Module::class, 'site_modules')
            ->withPivot(['is_active', 'deployment_status', 'last_installation_id', 'installed_version', 'version_reported_at', 'activated_at'])
            ->withTimestamps();
    }

    /** ADR-087: platform assets (templates, widgets) unlocked for this site one by one. */
    public function assetGrants()
    {
        return $this->hasMany(\App\Models\SiteAssetGrant::class);
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
    public function platformAgent()
    {
        return $this->hasOne(\App\Models\PlatformSiteAgent::class)->where('is_active', true);
    }

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

    // ── Lifecycle Relations ──────────────────────────────────────────────────

    public function lifecycleLogs()
    {
        return $this->hasMany(\App\Models\SiteLifecycleLog::class)->orderByDesc('created_at');
    }

    public function lifecycleInvoices()
    {
        return $this->hasMany(\App\Models\SiteLifecycleInvoice::class)->orderByDesc('created_at');
    }

    // ── Lifecycle State Helpers ──────────────────────────────────────────────

    public function getLifecycleStatus(): string
    {
        return $this->lifecycle_status ?? self::LIFECYCLE_ACTIVE;
    }

    public function isLifecycleActive(): bool
    {
        return $this->getLifecycleStatus() === self::LIFECYCLE_ACTIVE;
    }

    public function isInGracePeriod(): bool
    {
        return $this->getLifecycleStatus() === self::LIFECYCLE_GRACE_PERIOD;
    }

    public function isSuspended(): bool
    {
        return $this->getLifecycleStatus() === self::LIFECYCLE_SUSPENDED;
    }

    public function isArchived(): bool
    {
        return $this->getLifecycleStatus() === self::LIFECYCLE_ARCHIVED;
    }

    public function isLifecycleDeleted(): bool
    {
        return $this->getLifecycleStatus() === self::LIFECYCLE_DELETED;
    }

    public function isPubliclyAccessible(): bool
    {
        return in_array($this->getLifecycleStatus(), [self::LIFECYCLE_ACTIVE, self::LIFECYCLE_GRACE_PERIOD], true);
    }

    public function lifecycleStatusLabel(): string
    {
        return \App\Models\SiteLifecycleInvoice::statusLabel($this->getLifecycleStatus());
    }

    public function lifecycleStatusBadgeClass(): string
    {
        return match ($this->getLifecycleStatus()) {
            self::LIFECYCLE_ACTIVE        => 'bg-green-100 text-green-800',
            self::LIFECYCLE_GRACE_PERIOD  => 'bg-yellow-100 text-yellow-800',
            self::LIFECYCLE_SUSPENDED     => 'bg-red-100 text-red-800',
            self::LIFECYCLE_ARCHIVED      => 'bg-gray-100 text-gray-700',
            self::LIFECYCLE_DELETED       => 'bg-black text-white',
            default                       => 'bg-gray-100 text-gray-600',
        };
    }

    /** Days remaining in grace period (null if not in grace period) */
    public function gracePeriodDaysRemaining(): ?int
    {
        if (! $this->isInGracePeriod() || ! $this->grace_period_ends_at) {
            return null;
        }
        return max(0, (int) now()->diffInDays($this->grace_period_ends_at, false));
    }

    /** Days since suspension (null if not suspended) */
    public function daysSinceSuspension(): ?int
    {
        if (! $this->isSuspended() || ! $this->suspended_at) {
            return null;
        }
        return (int) $this->suspended_at->diffInDays(now());
    }

    /** Days since archival (null if not archived) */
    public function daysSinceArchival(): ?int
    {
        if (! $this->isArchived() || ! $this->archived_at) {
            return null;
        }
        return (int) $this->archived_at->diffInDays(now());
    }

    public function pendingLifecycleInvoice(): ?\App\Models\SiteLifecycleInvoice
    {
        return $this->lifecycleInvoices()->where('status', 'pending')->first();
    }
}
