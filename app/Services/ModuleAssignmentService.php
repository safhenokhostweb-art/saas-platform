<?php
namespace App\Services;

use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\Site;
use App\Services\Modules\ModuleDeploymentRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سرویس اختصاص ماژول به tenant (Site).
 * قانون ساده: ماژول فعال = چک‌باکس در پنل ادمین پلتفرم روشن باشد.
 * تیک زدن = ماژول برای سایت فعال و در پنل مشتری نمایش داده می‌شود؛ برداشتن تیک = غیرفعال و مخفی.
 * لایسنس سایت (کلید یکجا) برای فعال‌سازی دسترسی به همان لیست ماژول‌های تیک‌خورده است.
 *
 * Phase 17-C — Part 3: enableForSite() no longer flips is_active directly.
 * Entitlement (is_active) and deployment (deployment_status) are separate
 * concerns now — see ModuleDeploymentRequestService's docblock — so enabling
 * a module dispatches a real CustomerInstallation Pipeline run instead of
 * just writing a database flag.
 *
 * Phase 17-D — this is now the single, official entry point every live
 * admin caller (SiteModuleController, SiteController, OrderFulfillmentService)
 * goes through to change a site's module entitlement. See enableForSite(),
 * disableForSite(), activatePaidPurchase(), and syncManualAssignmentsForSite().
 *
 * Phase 17-E — the actual CustomerInstallation Pipeline dispatch (lock,
 * conflict-check, remote-server resolution, job dispatch) now lives in
 * ModuleDeploymentRequestService, shared with SiteModuleUpgradeController.
 * This service still owns entitlement-layer decisions (canEnableForSite(),
 * ledger sync) — only the deployment mechanics moved.
 */
class ModuleAssignmentService
{
    public function __construct(
        protected ModulePurchaseService $purchaseService,
        protected ModuleLicenseService $licenseService,
        protected ModuleDeploymentRequestService $deploymentRequestService,
        protected SiteModuleEntitlementAuditor $entitlementAuditor,
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
        if (! Schema::hasTable('site_modules') || ! Schema::hasTable('modules')) {
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
     * ماژول‌های PAID فقط از طریق خرید (activatePaidPurchase) فعال می‌شوند.
     *
     * Phase 17-C — Part 3 / Phase 17-D: دیگر مستقیماً is_active را نمی‌نویسد.
     * بعد از عبور از چک‌های مجوز/لایسنس، ModuleDeploymentRequestService را صدا
     * می‌زند که یک CustomerInstallation واقعی می‌سازد و Pipeline را dispatch
     * می‌کند. site_modules.is_active فقط وقتی true می‌شود که این Dispatch
     * موفق شود — نه صرفاً به این خاطر که ادمین تیک زده است.
     *
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    public function enableForSite(Site $site, int $moduleId, ?int $grantedByUserId = null): array
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module) {
            return ['success' => false, 'error' => 'این ماژول یافت نشد یا قابل اختصاص به سایت نیست.'];
        }
        if (! $this->canEnableForSite($site, $moduleId)) {
            if ($module->isLicenseOnly()) {
                return ['success' => false, 'error' => 'برای فعال‌سازی این ماژول، ابتدا لایسنس معتبر برای این سایت صادر کنید.'];
            }
            if ($module->isPaidMarketplace()) {
                return ['success' => false, 'error' => 'این ماژول پولی است و فقط از طریق فروشگاه ماژول قابل خرید و فعال‌سازی است.'];
            }
            return ['success' => false, 'error' => 'این ماژول در حال حاضر قابل فعال‌سازی برای این سایت نیست.'];
        }

        $wasActive = $this->currentlyActive($site->id, $moduleId);

        $user = $grantedByUserId ? \App\Models\User::find($grantedByUserId) : null;
        $result = $this->deploymentRequestService->deployModule($site, $module, ModuleDeploymentRequestService::MODE_INSTALL, $user);

        if ($result['success']) {
            $this->entitlementAuditor->syncAfterPivotChange($site, $module, $wasActive, true);
        }

        return $result;
    }

    /**
     * Phase 17-D — Part 5: فعال‌سازی یک ماژول PAID بلافاصله پس از تکمیل
     * پرداخت (OrderFulfillmentService). برخلاف enableForSite()، اینجا
     * چک رایگان/لایسنس اعمال نمی‌شود — خودِ پرداخت واجد شرایط بودن را ثابت
     * کرده است. اما همان Guardهای مشترک Dispatcher (internal/retired،
     * تداخل نصب در حال اجرا، در دسترس بودن Release) همچنان اجرا می‌شوند.
     *
     * تفاوت مهم با enableForSite(): چون مشتری از قبل پول پرداخت کرده،
     * is_active همیشه true می‌شود — حتی اگر Dispatch (به هر دلیل زیرساختی)
     * شکست بخورد — تا دسترسی مشتری هرگز به یک خطای موقت زیرساخت گره نخورد.
     * deployment_status صادقانه نتیجهٔ واقعی Dispatch را نشان می‌دهد، برای
     * پیگیری عملیاتی بعدی.
     *
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    public function activatePaidPurchase(Site $site, int $moduleId, ?int $triggeredByUserId = null): array
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module || ! $module->isPaidMarketplace()) {
            return ['success' => false, 'error' => 'این متد فقط برای ماژول‌های پولی (Paid Marketplace) قابل استفاده است.'];
        }

        $user = $triggeredByUserId ? \App\Models\User::find($triggeredByUserId) : null;
        $result = $this->deploymentRequestService->deployModule($site, $module, ModuleDeploymentRequestService::MODE_INSTALL, $user);

        $existing = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $moduleId)->first();

        DB::table('site_modules')->updateOrInsert(
            ['site_id' => $site->id, 'module_id' => $moduleId],
            [
                'is_active' => true,
                'deployment_status' => $existing->deployment_status ?? ($result['success'] ? 'pending_install' : 'failed'),
                'activated_at' => $existing->activated_at ?? now(),
                'updated_at' => now(),
                'created_at' => $existing->created_at ?? now(),
            ]
        );

        return $result;
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
        if (Schema::hasColumn('modules', 'free_assignable') && Schema::hasColumn('modules', 'license_required')) {
            $query->where(function ($q) {
                $q->where('free_assignable', true)->orWhere('license_required', true);
            });
        } elseif (Schema::hasColumn('modules', 'free_assignable')) {
            $query->where('free_assignable', true);
        } elseif (Schema::hasColumn('modules', 'license_required')) {
            $query->where('license_required', true);
        } else {
            $query->whereRaw('0=1');
        }
        return $query->orderBy('title')->get();
    }

    /**
     * غیرفعال کردن ماژول برای سایت.
     *
     * Phase 17-D — Part 3: رفتار Entitlement قبلی حفظ شده — فقط is_active
     * را false می‌کند؛ deployment_status، installed_version، و
     * last_installation_id دست‌نخورده باقی می‌مانند (این‌ها تاریخچهٔ واقعی
     * استقرار کد را نشان می‌دهند و disable آن‌ها را باطل نمی‌کند — یک
     * re-enable بعدی دوباره از آن‌ها به‌عنوان مبنای Upgrade در برابر Fresh
     * Install استفاده می‌کند). هیچ Uninstall Pipeline جدیدی ساخته نمی‌شود.
     *
     * اگر همین لحظه یک نصب/بروزرسانی برای همین ماژول روی این سایت در حال
     * اجراست، disable رد می‌شود — چون Race با آن Pipeline (که ممکن است
     * چند لحظه بعد deployment_status را به installed تغییر دهد) می‌تواند
     * وضعیتی متناقض (is_active=false ولی نصب موفق) ایجاد کند.
     *
     * @return array{success: bool, error?: string}
     */
    public function disableForSite(Site $site, int $moduleId): array
    {
        $module = Module::find($moduleId);

        if ($this->hasConflictingInstallation($site->id, $moduleId)) {
            return [
                'success' => false,
                'error' => 'یک نصب یا بروزرسانی برای همین ماژول روی این سایت در حال اجراست — تا پایان آن صبر کنید.',
            ];
        }

        $wasActive = $this->currentlyActive($site->id, $moduleId);

        DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('module_id', $moduleId)
            ->update(['is_active' => false]);

        if ($module) {
            $this->entitlementAuditor->syncAfterPivotChange($site, $module, $wasActive, false);
        }

        return ['success' => true];
    }

    /**
     * Phase 17-D — Part 4: یک مسیر مشترک برای فرم‌های Bulk (چند ماژول
     * هم‌زمان) — استفاده‌شده توسط SiteModuleController::update() و
     * SiteController::update() — تا منطق diff+enable/disable یک‌بار نوشته
     * شود، نه در هر Controller جداگانه.
     *
     * فقط ماژول‌های داده‌شده در $assignableModuleIds را لمس می‌کند — هیچ
     * site_module دیگری (مثلاً ماژول‌های PAID خریداری‌شده که در این فرم
     * نمایش داده نمی‌شوند) دست‌نخورده باقی می‌ماند؛ هرگز sync() سراسری
     * صدا زده نمی‌شود.
     *
     * @param array<int> $assignableModuleIds شناسهٔ ماژول‌هایی که در این فرم قابل مشاهده/تغییرند
     * @param array<int> $requestedActiveModuleIds زیرمجموعه‌ای که کاربر می‌خواهد فعال باشد
     * @return array<int, array{module_id:int, slug:string, title:string, result:string, error?:string}>
     */
    public function syncManualAssignmentsForSite(
        Site $site,
        array $assignableModuleIds,
        array $requestedActiveModuleIds,
        ?int $actingUserId = null
    ): array {
        $requestedActive = array_map('intval', $requestedActiveModuleIds);
        $modules = Module::whereIn('id', $assignableModuleIds)->get()->keyBy('id');

        $results = [];

        foreach ($assignableModuleIds as $moduleId) {
            $module = $modules->get($moduleId);
            if (! $module) {
                continue;
            }

            $wantsActive = in_array($moduleId, $requestedActive, true);
            $isActive = $this->currentlyActive($site->id, $moduleId);

            if ($wantsActive === $isActive) {
                $results[] = [
                    'module_id' => $moduleId, 'slug' => $module->slug, 'title' => $module->title,
                    'result' => 'unchanged',
                ];
                continue;
            }

            if ($wantsActive) {
                $outcome = $this->enableForSite($site, $moduleId, $actingUserId);
                $results[] = [
                    'module_id' => $moduleId, 'slug' => $module->slug, 'title' => $module->title,
                    'result' => $outcome['success'] ? 'enabled' : 'failed',
                    'error' => $outcome['error'] ?? null,
                ];
                continue;
            }

            $outcome = $this->disableForSite($site, $moduleId);
            $results[] = [
                'module_id' => $moduleId, 'slug' => $module->slug, 'title' => $module->title,
                'result' => $outcome['success'] ? 'disabled' : 'failed',
                'error' => $outcome['error'] ?? null,
            ];
        }

        return $results;
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

    private function currentlyActive(int $siteId, int $moduleId): bool
    {
        return (bool) DB::table('site_modules')
            ->where('site_id', $siteId)
            ->where('module_id', $moduleId)
            ->value('is_active');
    }

    /**
     * Phase 17-D — Part 3: آیا همین الان یک CustomerInstallation
     * (pending/running/rolling_back) برای همین سایت متعلق به همین ماژول در
     * جریان است؟ محدود به همین ماژول (نه کل سایت) — یک نصب/آپدیت ماژول
     * دیگر روی همین سایت نباید مانع غیرفعال‌سازی این ماژول شود.
     */
    private function hasConflictingInstallation(int $siteId, int $moduleId): bool
    {
        $slug = Module::find($moduleId)?->slug;
        if (! $slug) {
            return false;
        }

        return CustomerInstallation::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'running', 'rolling_back'])
            ->whereIn('install_mode', ['MODE_MODULE_INSTALL', 'MODE_MODULE_UPDATE'])
            ->get()
            ->contains(fn (CustomerInstallation $i) => ($i->metadata['module_slug'] ?? null) === $slug);
    }
}
