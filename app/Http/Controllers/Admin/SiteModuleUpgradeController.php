<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\AuthorizationService;
use App\Services\ModuleUpdateEligibilityService;
use App\Services\Modules\ModuleDeploymentRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 13-K — Upgrade Request & Approval Flow.
 *
 * The trigger for the MODE_MODULE_UPDATE pipeline (13-G..13-J). Creates
 * and dispatches, never itself performs any release, packaging, or
 * pipeline logic — those stay exclusively 12-F/13-H/13-I/13-J's jobs.
 *
 * Reuses the Wave 5.2 two-step confirm shape (reason + explicit
 * checkbox, no mutation on the first view) and CustomerDeploymentController's
 * create+dispatch pattern verbatim.
 *
 * Phase 13-M5 — every action additionally requires the `tenant.upgrade.execute`
 * permission, as a second layer on top of (never a replacement for) the
 * existing ['auth','admin'] middleware every other admin route uses. Plain
 * admin/staff access alone is no longer sufficient to reach this specific,
 * higher-risk flow (real mysqldump/unzip/`artisan migrate --force` against a
 * customer's live server) — see AuthorizationService::can().
 *
 * Phase 17-E — store() no longer builds the CustomerInstallation itself.
 * The lock, conflict-check, remote-server resolution, row creation, and job
 * dispatch (previously duplicated here and in ModuleInstallationDispatcher)
 * now live in ModuleDeploymentRequestService::upgradeModule(), shared with
 * every other module-deployment caller. This controller keeps only what is
 * genuinely upgrade-specific: the permission gate, the semver/compatibility/
 * support-license eligibility check (resolveEligibleUpgrade(), via
 * ModuleUpdateEligibilityService), the reason+confirmation UX, and activity
 * logging.
 */
class SiteModuleUpgradeController extends Controller
{
    public function __construct(
        protected ModuleUpdateEligibilityService $eligibilityService,
        protected AuthorizationService $authorizationService,
        protected ModuleDeploymentRequestService $deploymentRequestService,
    ) {}

    /**
     * Phase 13-M5 — second authorization layer, on top of the existing
     * ['auth','admin'] middleware. AuthorizationService::can() already
     * short-circuits true for super_admin (User::isSuperAdmin()), so no
     * separate super_admin branch is needed here.
     */
    private function authorizeUpgrade(): void
    {
        if (!$this->authorizationService->can(Auth::user(), 'tenant.upgrade.execute')) {
            abort(403, 'شما اجازهٔ اجرای بروزرسانی ماژول را ندارید.');
        }
    }

    /**
     * Phase 17-E — delegates to the shared service's lock key so tests
     * (and any external caller) exercise the exact same lock
     * ModuleDeploymentRequestService::upgradeModule() acquires. Kept as a
     * static method on this controller for backward compatibility with
     * existing lock-isolation tests.
     */
    public static function lockKey(int $siteId): string
    {
        return ModuleDeploymentRequestService::lockKey($siteId);
    }

    /**
     * List module updates currently eligible for this site.
     */
    public function index(Site $site)
    {
        $this->authorizeUpgrade();

        $updates = $this->eligibleUpgrades($site);

        return view('admin.sites.module-upgrades.index', compact('site', 'updates'));
    }

    /**
     * Confirmation page for one specific upgrade. No mutation.
     */
    public function confirm(Site $site, string $moduleSlug)
    {
        $this->authorizeUpgrade();

        $upgrade = $this->resolveEligibleUpgrade($site, $moduleSlug);

        if (!$upgrade) {
            return redirect()
                ->route('admin.sites.module-upgrades.index', $site)
                ->with('error', 'این بروزرسانی دیگر برای این سایت در دسترس نیست.');
        }

        return view('admin.sites.module-upgrades.confirm', [
            'site' => $site,
            'upgrade' => $upgrade,
        ]);
    }

    /**
     * Validate the confirmation, then delegate the actual deployment
     * (lock, conflict-check, remote-server resolution, CustomerInstallation
     * creation, job dispatch) to ModuleDeploymentRequestService.
     */
    public function store(Request $request, Site $site, string $moduleSlug)
    {
        $this->authorizeUpgrade();

        // Re-validate eligibility server-side at execution time, not just
        // trusting what the confirmation page showed a moment earlier. This
        // is upgrade-specific (semver/compatibility/support-license) and
        // stays here — the shared service only re-checks deployment
        // mechanics (internal/license/conflict/package/remote-server), not
        // *which* version is an appropriate upgrade target.
        $upgrade = $this->resolveEligibleUpgrade($site, $moduleSlug);

        if (!$upgrade) {
            return redirect()
                ->route('admin.sites.module-upgrades.index', $site)
                ->with('error', 'این بروزرسانی دیگر برای این سایت در دسترس نیست.');
        }

        $request->validate([
            'reason' => 'required|string|min:5|max:1000',
            'confirmed_upgrade' => 'required|accepted',
        ], [
            'reason.required' => 'برای شروع بروزرسانی، ذکر دلیل الزامی است.',
            'reason.min' => 'دلیل باید حداقل ۵ نویسه باشد.',
            'confirmed_upgrade.required' => 'برای ادامه باید چک‌باکس تأیید را علامت بزنید.',
            'confirmed_upgrade.accepted' => 'برای ادامه باید چک‌باکس تأیید را علامت بزنید.',
        ]);

        $module = Module::where('slug', $moduleSlug)->first();
        if (!$module) {
            return redirect()
                ->route('admin.sites.module-upgrades.index', $site)
                ->with('error', 'این بروزرسانی دیگر برای این سایت در دسترس نیست.');
        }

        $reason = $request->input('reason');

        $result = $this->deploymentRequestService->upgradeModule(
            $site,
            $module,
            Auth::user(),
            ['metadata' => ['requested_reason' => $reason]]
        );

        if (!$result['success']) {
            return redirect()
                ->route('admin.sites.module-upgrades.index', $site)
                ->with('error', $result['error']);
        }

        $installation = $result['installation'];

        ActivityLogger::log(
            'module.upgrade_requested',
            "درخواست بروزرسانی ماژول {$upgrade['title']} برای سایت {$site->domain} به نسخهٔ {$upgrade['available_version']}",
            $site,
            [
                'module_slug' => $upgrade['slug'],
                'installed_version' => $upgrade['installed_version'],
                'target_version' => $upgrade['available_version'],
                'installation_id' => $installation->id,
                'reason' => $reason,
            ]
        );

        return redirect()
            ->route('admin.infrastructure.customer-deployment.installations.show', $installation)
            ->with('success', 'بروزرسانی ماژول در صف اجرا قرار گرفت.');
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{slug:string,title:string,installed_version:?string,available_version:string,update_type:string,module_release_id:?int,has_package:bool}>
     */
    private function eligibleUpgrades(Site $site): array
    {
        $updates = $this->eligibilityService->getAvailableModuleUpdates($site);
        $result = [];

        foreach ($updates as $u) {
            $module = Module::where('slug', $u['slug'])->first();
            if (!$module) {
                continue;
            }

            $release = ModuleRelease::where('module_id', $module->id)
                ->where('version', $u['available_version'])
                ->first();

            $result[] = array_merge($u, [
                'module_release_id' => $release?->id,
                'has_package' => (bool) $release?->package_sha256,
            ]);
        }

        return $result;
    }

    private function resolveEligibleUpgrade(Site $site, string $moduleSlug): ?array
    {
        foreach ($this->eligibleUpgrades($site) as $u) {
            if ($u['slug'] === $moduleSlug && $u['has_package'] && $u['module_release_id']) {
                return $u;
            }
        }

        return null;
    }
}
