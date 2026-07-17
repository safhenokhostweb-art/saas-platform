<?php

namespace App\Services\Modules;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ModuleLicenseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 17-C — Part 2/3: the one place that turns "an admin wants this
 * module active for this site" into a real CustomerInstallation Pipeline
 * run. Mirrors SiteModuleUpgradeController's established shape (per-site
 * lock, re-validate under the lock, no half-finished row left behind if
 * dispatch itself fails) rather than inventing a new pattern.
 *
 * Deliberately does NOT touch CustomerInstallationOrchestrator, Stages,
 * Provisioning, or Rollback — it only ever creates a CustomerInstallation
 * row and dispatches the existing job, exactly like
 * SiteModuleUpgradeController::store() already does for upgrades.
 */
class ModuleInstallationDispatcher
{
    public function __construct(
        private readonly ModuleLicenseService $licenseService,
    ) {}

    public static function lockKey(int $siteId): string
    {
        return "module-install-lock:{$siteId}";
    }

    /**
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    public function dispatch(Site $site, Module $module, ?int $triggeredByUserId = null): array
    {
        $lock = Cache::lock(self::lockKey($site->id), 10);

        $outcome = $lock->get(function () use ($site, $module, $triggeredByUserId) {
            return $this->dispatchUnderLock($site, $module, $triggeredByUserId);
        });

        if ($outcome === false) {
            return ['success' => false, 'error' => 'یک نصب یا بروزرسانی دیگر هم‌اکنون برای این سایت در حال اجراست.'];
        }

        return $outcome;
    }

    /**
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    private function dispatchUnderLock(Site $site, Module $module, ?int $triggeredByUserId): array
    {
        // Re-fetch fresh, under the lock — mirrors SiteModuleUpgradeController
        // re-checking eligibility under its own lock rather than trusting a
        // check made moments earlier by the caller.
        $module = Module::assignableToSite()->find($module->id);
        if (!$module) {
            return ['success' => false, 'error' => 'این ماژول قابل اختصاص به سایت نیست.'];
        }

        // Phase 17-B guard is structural (scopeAssignableToSite already
        // excludes TYPE_INTERNAL), but this is the actual dispatch trigger
        // point — a second, explicit check here costs nothing and means
        // this class never relies solely on a query scope elsewhere staying
        // correct.
        if ($module->type === Module::TYPE_INTERNAL) {
            Log::warning('module_install.internal_module_blocked', ['site_id' => $site->id, 'module_id' => $module->id]);
            return ['success' => false, 'error' => 'ماژول‌های داخلی (internal) هرگز برای سایت مشتری مستقر نمی‌شوند.'];
        }

        // Phase 17-D — Part 5: whether a PAID-marketplace module may be
        // activated here is a caller-specific business rule, not a
        // deployment-mechanics one: ModuleAssignmentService::enableForSite()
        // (manual free/license assign) already rejects it via
        // canEnableForSite() before ever reaching this class, while
        // activatePaidPurchase() (post-payment fulfillment) exists
        // specifically to deploy a paid module after purchase. This class
        // stays neutral about *why* it was called and only enforces the
        // checks that are universally true regardless of caller: internal
        // modules, license validity, install conflicts, package
        // availability, and a resolvable remote server.
        if ($module->isLicenseOnly() && !$this->licenseService->hasValidLicense($site, $module->id)) {
            return ['success' => false, 'error' => 'برای این ماژول لایسنس معتبری برای این سایت یافت نشد.'];
        }

        $alreadyRunning = CustomerInstallation::where('site_id', $site->id)
            ->whereIn('status', ['pending', 'running', 'rolling_back'])
            ->exists();

        if ($alreadyRunning) {
            return ['success' => false, 'error' => 'یک نصب یا بروزرسانی دیگر هم‌اکنون برای این سایت در حال اجراست.'];
        }

        $release = ModuleRelease::latestForModule($module->id);
        if (!$release || empty($release->package_sha256)) {
            return ['success' => false, 'error' => 'برای این ماژول هیچ بستهٔ نصب آماده‌ای ثبت نشده است.'];
        }

        $remoteServerId = $this->resolveRemoteServerId($site);
        if (!$remoteServerId) {
            return ['success' => false, 'error' => 'سرور راه‌دور شناخته‌شده‌ای برای این سایت یافت نشد — امکان استقرار نیست.'];
        }

        $existingPivot = DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->first();

        // The domain distinction Part 4 asks for: has this module's code
        // ever been successfully deployed to this site before? An
        // entitlement row with no installed_version yet (or no row at all)
        // means this is the module's first real deployment.
        $isFreshInstall = !$existingPivot || $existingPivot->installed_version === null;

        DB::table('site_modules')->updateOrInsert(
            ['site_id' => $site->id, 'module_id' => $module->id],
            [
                'is_active' => true,
                'deployment_status' => 'pending_install',
                'activated_at' => $existingPivot->activated_at ?? now(),
                'updated_at' => now(),
                'created_at' => $existingPivot->created_at ?? now(),
            ]
        );

        $customer = $site->customer;

        $installation = CustomerInstallation::create([
            'remote_server_id' => $remoteServerId,
            'installer_user_id' => $triggeredByUserId,
            'install_mode' => $isFreshInstall ? 'MODE_MODULE_INSTALL' : 'MODE_MODULE_UPDATE',
            'status' => 'pending',
            'site_id' => $site->id,
            'customer_company' => $customer?->name ?? $site->title,
            'customer_email' => $customer?->email ?? 'noreply@example.com',
            'customer_domain' => $site->domain,
            'request_payload' => [
                'company' => $customer?->name ?? $site->title,
                'owner_name' => $customer?->name ?? $site->title,
                'email' => $customer?->email ?? 'noreply@example.com',
                'phone' => $customer?->mobile ?? '',
                'domain' => $site->domain,
                'remote_server_id' => $remoteServerId,
                'install_mode' => $isFreshInstall ? 'MODE_MODULE_INSTALL' : 'MODE_MODULE_UPDATE',
            ],
            'metadata' => [
                'module_slug' => $module->slug,
                'module_release_id' => $release->id,
                'target_version' => $release->version,
            ],
        ]);

        try {
            CustomerInstallationPipelineJob::dispatch($installation->id)
                ->onConnection('customer-install')
                ->onQueue('customer-install');
        } catch (\Throwable $e) {
            // No half-finished state — mirrors SiteModuleUpgradeController::store().
            $installation->delete();

            DB::table('site_modules')
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->update(['deployment_status' => 'failed', 'updated_at' => now()]);

            Log::error('module_install.dispatch_failed', [
                'site_id' => $site->id,
                'module_id' => $module->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'شروع فرآیند نصب ماژول با خطا مواجه شد. لطفاً دوباره تلاش کنید.'];
        }

        DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->update(['last_installation_id' => $installation->id, 'updated_at' => now()]);

        return ['success' => true, 'installation' => $installation];
    }

    /**
     * Mirrors SiteModuleUpgradeController::resolveRemoteServerId() exactly —
     * the remote server a module deploys to is always the one the site's
     * own fresh install completed on.
     */
    private function resolveRemoteServerId(Site $site): ?int
    {
        return CustomerInstallation::where('site_id', $site->id)
            ->where('status', 'complete')
            ->latest()
            ->value('remote_server_id');
    }
}
