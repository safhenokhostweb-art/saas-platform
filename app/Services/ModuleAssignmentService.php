<?php
namespace App\Services;

use App\Models\Module;
use App\Models\Site;
use App\Services\Modules\ModuleInstallationDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * سرویس اختصاص ماژول به tenant (Site).
 * قانون ساده: ماژول فعال = چک‌باکس در پنل ادمین پلتفرم روشن باشد.
 * تیک زدن = ماژول برای سایت فعال و در پنل مشتری نمایش داده می‌شود؛ برداشتن تیک = غیرفعال و مخفی.
 * لایسنس سایت (کلید یکجا) برای فعال‌سازی دسترسی به همان لیست ماژول‌های تیک‌خورده است.
 *
 * Phase 17-C — Part 3: enableForSite() no longer flips is_active directly.
 * Entitlement (is_active) and deployment (deployment_status) are separate
 * concerns now — see ModuleInstallationDispatcher's docblock — so enabling a
 * module dispatches a real CustomerInstallation Pipeline run instead of just
 * writing a database flag.
 */
class ModuleAssignmentService
{
    public function __construct(
        protected ModulePurchaseService $purchaseService,
        protected ModuleLicenseService $licenseService,
        protected ModuleInstallationDispatcher $installationDispatcher,
    ) {}

    /**
     * ماژول‌های فعال سایت برای ارسال به tenant.
     * فقط ماژول‌هایی که در site_modules با is_active=1 هستند و ماژول در حالت غیر DISABLED است.
     * (FREE: تیک دستی | PAID: پس از خرید | LICENSE-ONLY: پس از صدور لایسنس)
     *
     * @return array<string>
     */
    public function getEnabledSlugsForSite(Site $site): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('site_modules') || ! \Illuminate\Support\Facades\Schema::hasTable('modules')) {
            return [];
        }

        $pivots = $site->modules()
            ->wherePivot('is_active', true)
            ->whereIn('type', [Module::TYPE_CORE, Module::TYPE_PAID])
            ->get();

        $slugs = [];
        foreach ($pivots as $module) {
            if ($module->isDistributionDisabled()) {
                continue;
            }
            $slugs[] = $module->slug;
        }
        return array_values(array_unique($slugs));
    }

    /**
     * آیا سایت ماژول مشخصی فعال دارد؟
     */
    public function isModuleEnabledForSite(Site $site, string $moduleSlug): bool
    {
        return in_array($moduleSlug, $this->getEnabledSlugsForSite($site), true);
    }

    /**
     * فعال کردن ماژول برای سایت.
     * فقط برای FREE ASSIGNABLE یا LICENSE-ONLY (با لایسنس معتبر) مجاز است.
     * ماژول‌های PAID فقط از طریق خرید فعال می‌شوند.
     *
     * Phase 17-C — Part 3: دیگر مستقیماً is_active را نمی‌نویسد. بعد از عبور
     * از چک‌های مجوز/لایسنس، ModuleInstallationDispatcher را صدا می‌زند که
     * یک CustomerInstallation واقعی می‌سازد و Pipeline را dispatch می‌کند.
     * site_modules.is_active فقط وقتی true می‌شود که این Dispatch موفق شود —
     * نه صرفاً به این خاطر که ادمین تیک زده است. اگر Dispatch شکست بخورد،
     * وضعیت به deployment_status=failed می‌رود (توسط Dispatcher نوشته
     * می‌شود)، نه یک site_module فعال بدون استقرار واقعی.
     */
    public function enableForSite(Site $site, int $moduleId, ?int $grantedByUserId = null): bool
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module) {
            return false;
        }
        if (! $this->canEnableForSite($site, $moduleId)) {
            return false;
        }

        $result = $this->installationDispatcher->dispatch($site, $module, $grantedByUserId);

        return $result['success'];
    }

    /**
     * آیا می‌توان ماژول را برای سایت فعال کرد؟
     * FREE ASSIGNABLE: بله. PAID: خیر (فقط از طریق خرید). LICENSE-ONLY: فقط اگر لایسنس معتبر داشته باشد.
     */
    public function canEnableForSite(Site $site, int $moduleId): bool
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module) {
            return false;
        }
        if ($module->isFreeAssignable()) {
            return true;
        }
        if ($module->isPaidMarketplace()) {
            return false;
        }
        if ($module->isLicenseOnly()) {
            return $this->licenseService->hasValidLicense($site, $moduleId);
        }
        return false;
    }

    /**
     * ماژول‌هایی که در پنل «ماژول‌های سایت» برای اختصاص دستی نمایش داده می‌شوند.
     * فقط FREE ASSIGNABLE و LICENSE-ONLY (ماژول‌های PAID فقط در فروشگاه tenant).
     */
    public function modulesForManualAssign(): \Illuminate\Database\Eloquent\Collection
    {
        $query = Module::assignableToSite()->where('is_active', true);
        if (\Illuminate\Support\Facades\Schema::hasColumn('modules', 'free_assignable') && \Illuminate\Support\Facades\Schema::hasColumn('modules', 'license_required')) {
            $query->where(function ($q) {
                $q->where('free_assignable', true)->orWhere('license_required', true);
            });
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('modules', 'free_assignable')) {
            $query->where('free_assignable', true);
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('modules', 'license_required')) {
            $query->where('license_required', true);
        } else {
            $query->whereRaw('0=1');
        }
        return $query->orderBy('title')->get();
    }

    /**
     * غیرفعال کردن ماژول برای سایت.
     */
    public function disableForSite(Site $site, int $moduleId): void
    {
        DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('module_id', $moduleId)
            ->update(['is_active' => false]);
    }

    /**
     * وضعیت فعال‌سازی ماژول برای سایت.
     */
    public function getActivationStatus(Site $site): array
    {
        $pivots = DB::table('site_modules')
            ->join('modules', 'site_modules.module_id', '=', 'modules.id')
            ->where('site_modules.site_id', $site->id)
            ->whereIn('modules.type', [Module::TYPE_CORE, Module::TYPE_PAID])
            ->select(
                'modules.slug',
                'site_modules.is_active',
                'site_modules.activated_at'
            )
            ->get();

        $out = [];
        foreach ($pivots as $p) {
            $out[$p->slug] = [
                'enabled' => (bool) $p->is_active,
                'activated_at' => $p->activated_at,
            ];
        }
        return $out;
    }
}
