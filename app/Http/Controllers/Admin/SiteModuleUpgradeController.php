<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\ModuleUpdateEligibilityService;
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
 * create+dispatch pattern verbatim. Gated by the same ['auth','admin']
 * middleware every other admin route already uses — no new permission.
 */
class SiteModuleUpgradeController extends Controller
{
    public function __construct(
        protected ModuleUpdateEligibilityService $eligibilityService,
    ) {}

    /**
     * List module updates currently eligible for this site.
     */
    public function index(Site $site)
    {
        $updates = $this->eligibleUpgrades($site);

        return view('admin.sites.module-upgrades.index', compact('site', 'updates'));
    }

    /**
     * Confirmation page for one specific upgrade. No mutation.
     */
    public function confirm(Site $site, string $moduleSlug)
    {
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
     * Validate the confirmation, create the CustomerInstallation
     * (MODE_MODULE_UPDATE) row, and dispatch the existing pipeline job.
     */
    public function store(Request $request, Site $site, string $moduleSlug)
    {
        // Re-validate eligibility server-side at execution time, not just
        // trusting what the confirmation page showed a moment earlier.
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

        $remoteServerId = $this->resolveRemoteServerId($site);
        if (!$remoteServerId) {
            return back()
                ->withInput()
                ->with('error', 'سرور راه‌دور شناخته‌شده‌ای برای این سایت یافت نشد — امکان بروزرسانی نیست.');
        }

        $customer = $site->customer;

        $installation = CustomerInstallation::create([
            'remote_server_id' => $remoteServerId,
            'installer_user_id' => Auth::id(),
            'install_mode' => 'MODE_MODULE_UPDATE',
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
                'install_mode' => 'MODE_MODULE_UPDATE',
            ],
            'metadata' => [
                'module_slug' => $upgrade['slug'],
                'module_release_id' => $upgrade['module_release_id'],
                'target_version' => $upgrade['available_version'],
                'requested_reason' => $request->input('reason'),
            ],
        ]);

        CustomerInstallationPipelineJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        ActivityLogger::log(
            'module.upgrade_requested',
            "درخواست بروزرسانی ماژول {$upgrade['title']} برای سایت {$site->domain} به نسخهٔ {$upgrade['available_version']}",
            $site,
            [
                'module_slug' => $upgrade['slug'],
                'installed_version' => $upgrade['installed_version'],
                'target_version' => $upgrade['available_version'],
                'installation_id' => $installation->id,
                'reason' => $request->input('reason'),
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

    private function resolveRemoteServerId(Site $site): ?int
    {
        return CustomerInstallation::where('site_id', $site->id)
            ->where('status', 'complete')
            ->latest()
            ->value('remote_server_id');
    }
}
