<?php

namespace App\Services\Modules;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ModuleLicenseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;

/**
 * Phase 17-E — the single place that turns "deploy this module's code to
 * this site" into a real CustomerInstallation Pipeline run, for every
 * caller (Admin UI manual-assign, Admin UI upgrade-request, payment
 * fulfillment, and any future marketplace/API caller).
 *
 * Absorbs the mechanics previously duplicated between
 * ModuleInstallationDispatcher::dispatchUnderLock() and
 * SiteModuleUpgradeController::store(): per-site lock, re-validate under
 * the lock, resolve the remote server, create the CustomerInstallation row,
 * dispatch the pipeline job, and leave no half-finished state if dispatch
 * itself fails.
 *
 * Deliberately does NOT touch CustomerInstallationOrchestrator, Stages,
 * Provisioning, Rollback, or any deployment driver — exactly like the two
 * classes it replaces, it only ever creates a CustomerInstallation row and
 * dispatches the existing job.
 *
 * Caller-specific concerns stay with the caller and are never absorbed
 * here: permission gates, activity logging, reason/confirmation UX, and
 * eligibility computation that differs by *why* the deploy was requested
 * (e.g. SiteModuleUpgradeController's semver/compatibility/support-license
 * eligibility vs. a fresh install's license/type checks).
 */
class ModuleDeploymentRequestService
{
    public const MODE_INSTALL = 'install';
    public const MODE_UPGRADE = 'upgrade';
    public const MODE_RETRY = 'retry';
    public const MODE_REINSTALL = 'reinstall';

    public function __construct(
        private readonly ModuleLicenseService $licenseService,
        private readonly PlatformCompatibilityService $compatibilityService,
        private readonly ModuleDeploymentDependencyResolver $dependencyResolver,
    ) {}

    public static function lockKey(int $siteId): string
    {
        return "module-deployment-lock:{$siteId}";
    }

    /**
     * Install, retry, or reinstall a module's code onto a site.
     *
     * - install:    module has never been deployed to this site (or its
     *               prior deployment_status is 'failed' with no
     *               installed_version) — creates MODE_MODULE_INSTALL.
     * - retry:      an explicit re-attempt after a prior 'failed' deploy —
     *               same mechanics as install, kept as a distinct $mode so
     *               callers can express intent and so it is rejected if
     *               the module is not actually in a failed state.
     * - reinstall:  module already has installed_version — re-deploys the
     *               same/current release. Uses MODE_MODULE_UPDATE (the
     *               pipeline has no separate reinstall mode; this mirrors
     *               how an update is already just "deploy a release on top
     *               of an existing installation").
     *
     * @param  array{metadata?: array<string, mixed>}  $options  'metadata' is merged into the CustomerInstallation.metadata JSON (e.g. a caller-specific requested_reason); everything else is reserved for future callers (marketplace/API), unused today
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    public function deployModule(Site $site, Module $module, string $mode = self::MODE_INSTALL, ?User $user = null, array $options = []): array
    {
        if (!in_array($mode, [self::MODE_INSTALL, self::MODE_RETRY, self::MODE_REINSTALL], true)) {
            return ['success' => false, 'error' => 'حالت استقرار نامعتبر است.'];
        }

        $lock = Cache::lock(self::lockKey($site->id), 10);

        $outcome = $lock->get(function () use ($site, $module, $mode, $user, $options) {
            return $this->deployUnderLock($site, $module, $mode, $user?->id, $options['metadata'] ?? []);
        });

        if ($outcome === false) {
            return ['success' => false, 'error' => 'یک نصب یا بروزرسانی دیگر هم‌اکنون برای این سایت در حال اجراست.'];
        }

        return $outcome;
    }

    /**
     * Upgrade an already-installed module to its latest eligible release.
     *
     * Takes (Site, Module) rather than a SiteModule model — this codebase
     * has no Eloquent model for the site_modules pivot; it is accessed via
     * DB::table() everywhere, and introducing one is out of scope for this
     * phase (no unrequested architecture changes).
     *
     * $options accepts the same 'metadata' key as deployModule() — added
     * beyond the originally suggested (Site, Module, ?User) signature so
     * SiteModuleUpgradeController can keep attaching its 'requested_reason'
     * to the CustomerInstallation row, exactly as it did before this phase.
     *
     * @param  array{metadata?: array<string, mixed>}  $options
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    public function upgradeModule(Site $site, Module $module, ?User $user = null, array $options = []): array
    {
        $lock = Cache::lock(self::lockKey($site->id), 10);

        $outcome = $lock->get(function () use ($site, $module, $user, $options) {
            return $this->deployUnderLock($site, $module, self::MODE_UPGRADE, $user?->id, $options['metadata'] ?? []);
        });

        if ($outcome === false) {
            return ['success' => false, 'error' => 'یک نصب یا بروزرسانی دیگر هم‌اکنون برای این سایت در حال اجراست.'];
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $extraMetadata  merged into CustomerInstallation.metadata (e.g. requested_reason)
     * @return array{success: bool, error?: string, installation?: CustomerInstallation}
     */
    private function deployUnderLock(Site $site, Module $module, string $mode, ?int $triggeredByUserId, array $extraMetadata = []): array
    {
        // Re-fetch fresh, under the lock — never trust a check made moments
        // earlier by the caller.
        $module = Module::assignableToSite()->find($module->id);
        if (!$module) {
            return ['success' => false, 'error' => 'این ماژول قابل اختصاص به سایت نیست.'];
        }

        // Structural (scopeAssignableToSite already excludes TYPE_INTERNAL
        // and retired modules), but this is the actual dispatch trigger
        // point — a second, explicit check costs nothing and means this
        // class never relies solely on a query scope elsewhere staying
        // correct.
        if ($module->type === Module::TYPE_INTERNAL) {
            Log::warning('module_deployment.internal_module_blocked', ['site_id' => $site->id, 'module_id' => $module->id]);
            return ['success' => false, 'error' => 'ماژول‌های داخلی (internal) هرگز برای سایت مشتری مستقر نمی‌شوند.'];
        }

        if ($module->isDistributionDisabled()) {
            return ['success' => false, 'error' => 'این ماژول در حال حاضر برای استقرار در دسترس نیست.'];
        }

        // Whether a PAID-marketplace module may be deployed here is a
        // caller-specific business rule, not a deployment-mechanics one:
        // ModuleAssignmentService::enableForSite() (manual free/license
        // assign) already rejects it via canEnableForSite() before ever
        // reaching this class, while activatePaidPurchase() (post-payment
        // fulfillment) exists specifically to deploy a paid module after
        // purchase. This class stays neutral about *why* it was called.
        if ($module->isLicenseOnly() && !$this->licenseService->hasValidLicense($site, $module->id)) {
            return ['success' => false, 'error' => 'برای این ماژول لایسنس معتبری برای این سایت یافت نشد.'];
        }

        $alreadyRunning = CustomerInstallation::where('site_id', $site->id)
            ->whereIn('status', ['pending', 'running', 'rolling_back'])
            ->exists();

        if ($alreadyRunning) {
            return ['success' => false, 'error' => 'یک نصب یا بروزرسانی دیگر هم‌اکنون برای این سایت در حال اجراست.'];
        }

        // Phase 17-H — Part 7: only a published release is ever eligible
        // for install/upgrade/reinstall/retry — a draft/validated/built/
        // deprecated release must never be selected here regardless of
        // its version number, even if package_sha256 happens to be set.
        $release = ModuleRelease::latestPublishedForModule($module->id);
        if (!$release || empty($release->package_sha256)) {
            return ['success' => false, 'error' => 'برای این ماژول هیچ بستهٔ نصب آماده و منتشرشده‌ای ثبت نشده است.'];
        }

        // Phase 17-F — Part 4: every mode (install/upgrade/reinstall/retry)
        // must pass this check, not just the Upgrade UI's own eligibility
        // computation (ModuleUpdateEligibilityService) — a fresh install of
        // a platform-incompatible module was previously never checked at
        // all. Nothing is mutated yet at this point: no site_modules write,
        // no CustomerInstallation row, so a rejection here leaves no state
        // to clean up.
        $compatibility = $this->compatibilityService->check($module->slug, $release->version);
        if (!$compatibility['passed']) {
            Log::warning('module_deployment.incompatible', [
                'site_id' => $site->id, 'module_id' => $module->id,
                'version' => $release->version, 'errors' => $compatibility['errors'],
            ]);
            ActivityLogger::log(
                'module.deployment_incompatible',
                "استقرار ماژول {$module->title} روی سایت {$site->domain} به دلیل ناسازگاری با پلتفرم رد شد.",
                $site,
                ['module_slug' => $module->slug, 'target_version' => $release->version, 'errors' => $compatibility['errors']]
            );
            return ['success' => false, 'error' => 'این نسخه از ماژول با پلتفرم فعلی سازگار نیست: ' . implode('؛ ', $compatibility['errors'])];
        }

        // Phase 17-F — Part 5: reject if a declared module.json dependency
        // isn't already installed and active on this site. Same
        // no-mutation-yet guarantee as the compatibility check above.
        $dependencyCheck = $this->dependencyResolver->resolve($site, $module);
        if (!$dependencyCheck['valid']) {
            Log::warning('module_deployment.dependency_unresolved', [
                'site_id' => $site->id, 'module_id' => $module->id, 'errors' => $dependencyCheck['errors'],
            ]);
            ActivityLogger::log(
                'module.deployment_dependency_unresolved',
                "استقرار ماژول {$module->title} روی سایت {$site->domain} به دلیل وابستگی حل‌نشده رد شد.",
                $site,
                ['module_slug' => $module->slug, 'errors' => $dependencyCheck['errors']]
            );
            return ['success' => false, 'error' => implode('؛ ', $dependencyCheck['errors'])];
        }

        $remoteServerId = $this->resolveRemoteServerId($site);
        if (!$remoteServerId) {
            return ['success' => false, 'error' => 'سرور راه‌دور شناخته‌شده‌ای برای این سایت یافت نشد — امکان استقرار نیست.'];
        }

        $existingPivot = DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->first();

        // Has this module's code ever been successfully deployed to this
        // site before? An entitlement row with no installed_version yet
        // (or no row at all) means this is the module's first real
        // deployment, regardless of which $mode the caller asked for.
        $isFreshInstall = !$existingPivot || $existingPivot->installed_version === null;

        if ($mode === self::MODE_RETRY && (!$existingPivot || $existingPivot->deployment_status !== 'failed')) {
            return ['success' => false, 'error' => 'این ماژول در وضعیت خطا نیست — امکان تلاش مجدد وجود ندارد.'];
        }

        if (in_array($mode, [self::MODE_UPGRADE, self::MODE_REINSTALL], true) && $isFreshInstall) {
            return ['success' => false, 'error' => 'این ماژول هنوز روی این سایت نصب نشده است — ابتدا باید نصب شود.'];
        }

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
        $installMode = $isFreshInstall ? 'MODE_MODULE_INSTALL' : 'MODE_MODULE_UPDATE';

        $installation = CustomerInstallation::create([
            'remote_server_id' => $remoteServerId,
            'installer_user_id' => $triggeredByUserId,
            'install_mode' => $installMode,
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
                'install_mode' => $installMode,
            ],
            'metadata' => array_merge([
                'module_slug' => $module->slug,
                'module_release_id' => $release->id,
                'target_version' => $release->version,
                'deployment_request_mode' => $mode,
            ], $extraMetadata),
        ]);

        try {
            CustomerInstallationPipelineJob::dispatch($installation->id)
                ->onConnection('customer-install')
                ->onQueue('customer-install');
        } catch (\Throwable $e) {
            // No half-finished state left behind for any caller.
            $installation->delete();

            DB::table('site_modules')
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->update(['deployment_status' => 'failed', 'updated_at' => now()]);

            Log::error('module_deployment.dispatch_failed', [
                'site_id' => $site->id,
                'module_id' => $module->id,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'شروع فرآیند استقرار ماژول با خطا مواجه شد. لطفاً دوباره تلاش کنید.'];
        }

        DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->update(['last_installation_id' => $installation->id, 'updated_at' => now()]);

        return ['success' => true, 'installation' => $installation];
    }

    /**
     * The remote server a module deploys to is always the one the site's
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
