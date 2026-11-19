<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartOnboardingRequest;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Theme;
use App\Services\ActivityLogger;
use App\Services\Infrastructure\ServerCapacityService;
use App\Services\Onboarding\OnboardingDomainPolicy;
use App\Services\Onboarding\SiteOnboardingService;
use App\Services\Onboarding\SiteTemplateBinder;
use App\Services\ThemeBuilder\ThemeFleetUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ADR-088 — the unified customer & site onboarding wizard. Replaces the
 * customers/create, sites/create, sites/wizard and customer-deployment
 * wizard entry points (they redirect here). Transport only: every decision
 * is SiteOnboardingService's.
 */
class OnboardingWizardController extends Controller
{
    public function __construct(
        private readonly SiteOnboardingService $onboarding,
        private readonly OnboardingDomainPolicy $domains,
        private readonly ThemeFleetUpgradeService $fleetService,
        private readonly ServerCapacityService $capacityService,
    ) {}

    public function create(Request $request): View
    {
        $options = $this->onboarding->wizardOptions();
        $prefill = $request->filled('mobile') ? $this->onboarding->lookupCustomer((string) $request->query('mobile')) : null;
        $fleetUpdates = $this->fleetService->discoverAvailableUpdates();
        $auditHistory = $this->fleetService->getAuditHistory();
        $allSites = \App\Models\Site::whereNotNull('domain')->orderBy('id')->get();
        $activeTab = $request->query('tab', 'onboarding');

        return view('admin.onboarding.wizard', compact('options', 'prefill', 'fleetUpdates', 'auditHistory', 'allSites', 'activeTab'));
    }

    public function lookupCustomer(Request $request): JsonResponse
    {
        $request->validate(['mobile' => 'required|string|max:20']);

        return response()->json($this->onboarding->lookupCustomer((string) $request->query('mobile')));
    }

    public function checkDomain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode'   => ['required', Rule::in([OnboardingDomainPolicy::MODE_SUBDOMAIN, OnboardingDomainPolicy::MODE_CUSTOM])],
            'domain' => ['required', 'string', 'max:253'],
        ]);

        return response()->json($this->domains->check($data['mode'], $data['domain']));
    }

    public function serverCapacity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'server_id'    => ['required', 'integer', 'exists:customer_remote_servers,id'],
            'ram_mb'       => ['nullable', 'integer', 'min:256'],
            'disk_gb'      => ['nullable', 'numeric', 'min:1'],
            'soft_disk_gb' => ['nullable', 'numeric', 'min:0.5'],
            'cpu_cores'    => ['nullable', 'numeric', 'min:0.25'],
        ]);

        $server = CustomerRemoteServer::findOrFail($data['server_id']);
        $capacity = $this->capacityService->getCapacity($server);

        $validation = null;
        if (isset($data['ram_mb']) && isset($data['disk_gb'])) {
            $validation = $this->capacityService->validateAllocation(
                $server,
                (int) $data['ram_mb'],
                (float) $data['disk_gb'],
                isset($data['soft_disk_gb']) ? (float) $data['soft_disk_gb'] : null,
                isset($data['cpu_cores']) ? (float) $data['cpu_cores'] : 1.0
            );
        }

        return response()->json([
            'capacity'   => $capacity,
            'validation' => $validation,
        ]);
    }

    public function suggestInfrastructure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'server_id'  => ['required', 'integer', 'exists:customer_remote_servers,id'],
            'domain'     => ['required', 'string', 'max:253'],
            'site_title' => ['nullable', 'string', 'max:120'],
        ]);

        $server = CustomerRemoteServer::findOrFail($data['server_id']);
        $suggested = $this->onboarding->suggestInfrastructure($server, $data['domain'], $data['site_title'] ?? '');

        return response()->json($suggested);
    }

    public function store(StartOnboardingRequest $request): RedirectResponse
    {
        $installation = $this->onboarding->start($request->validated(), $request->user());

        return redirect()
            ->route('admin.infrastructure.customer-deployment.installations.show', $installation)
            ->with('success', "راه‌اندازی «{$installation->customer_domain}» آغاز شد. همهٔ مراحل — از سرور و دامنه تا لایسنس و قالب — به‌صورت خودکار اجرا می‌شوند.");
    }

    /** Re-run the theme binding of a completed onboarding (assign + push). */
    public function retryTemplate(CustomerInstallation $installation, SiteTemplateBinder $binder): RedirectResponse
    {
        $themeId = $binder->binding($installation)['theme_id'] ?? null;
        $theme = $themeId ? Theme::find($themeId) : null;
        if (! $installation->isComplete() || $theme === null) {
            return back()->with('error', 'این نصب قالبی برای اعمال ندارد یا هنوز کامل نشده است.');
        }

        $binder->markPendingPush($installation, $theme);
        $binder->dispatch($installation);

        ActivityLogger::log('onboarding.template_retry', "تلاش دوباره برای اعمال قالب روی {$installation->customer_domain}", $installation, [
            'installation_id' => $installation->id,
        ]);

        return back()->with('success', 'اعمال قالب دوباره در صف قرار گرفت.');
    }

    /** Dispatch non-destructive fleet update for a customer site. */
    public function applyFleetUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id'    => 'required|integer|exists:sites,id',
            'release_id' => 'required|integer|exists:theme_releases,id',
        ]);

        $site = \App\Models\Site::findOrFail($data['site_id']);
        $release = \App\Models\ThemeRelease::with('theme')->findOrFail($data['release_id']);

        try {
            $update = $this->fleetService->dispatchUpgrade($site, $release, $request->user()?->id);
            if ($update->status === \App\Models\ThemeFleetUpdate::STATUS_FAILED) {
                return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                    ->with('error', "ارتقای قالب برای «{$site->title}» ناموفق بود: " . $update->failure_reason);
            }

            return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                ->with('success', "به‌روزرسانی قالب «{$release->theme?->name}» به نسخه {$release->version} برای سایت «{$site->title}» با موفقیت اعمال شد و تمام صفحات و شخصی‌سازی‌های مشتری حفظ شدند.");
        } catch (\Throwable $e) {
            return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                ->with('error', "خطا در ارتقای قالب: " . $e->getMessage());
        }
    }

    /** Dispatch customer-site package update for selected site(s). */
    public function applyPackageFleetUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_ids'   => 'nullable|array',
            'site_ids.*' => 'integer|exists:sites,id',
            'site_id'    => 'nullable|integer|exists:sites,id',
        ]);

        $siteIds = $data['site_ids'] ?? [];
        if (!empty($data['site_id']) && !in_array($data['site_id'], $siteIds)) {
            $siteIds[] = (int) $data['site_id'];
        }

        if (empty($siteIds)) {
            return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                ->with('error', 'هیچ سایتی برای ارتقای پکیج انتخاب نشده است.');
        }

        // P0 safety (2026-11-19): only the genuinely active package. This used
        // to fall back to any (possibly inactive) package and, when none
        // existed, to INSERT an active one with a fabricated sha256/size.
        $package = \App\Models\CustomerInstallationPackage::active();
        if (!$package) {
            return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                ->with('error', 'هیچ بستهٔ نصب فعالی ثبت نشده است.');
        }

        $successCount = 0;
        $errors = [];

        foreach ($siteIds as $sId) {
            $site = \App\Models\Site::find($sId);
            if (!$site) {
                continue;
            }

            try {
                $update = $this->fleetService->dispatchPackageUpgrade($site, $package, $request->user()?->id);
                if ($update->status === \App\Models\ThemeFleetUpdate::STATUS_COMPLETED) {
                    $successCount++;
                } else {
                    $errors[] = "{$site->title}: {$update->failure_reason}";
                }
            } catch (\Throwable $e) {
                $errors[] = "{$site->title}: " . $e->getMessage();
            }
        }

        $msg = "ارتقای پکیج هسته و ویجت‌ها به نسخه {$package->version} روی {$successCount} سایت با موفقیت اعمال شد.";
        if (!empty($errors)) {
            $msg .= " (خطاها: " . implode(' | ', $errors) . ")";
        }

        return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
            ->with($successCount > 0 ? 'success' : 'error', $msg);
    }

    /** Dispatch one-click rollback to restore the customer site to its previous snapshot. */
    public function rollbackFleetUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'update_id' => 'required|integer|exists:theme_fleet_updates,id',
        ]);

        $targetUpdate = \App\Models\ThemeFleetUpdate::with(['site', 'theme', 'fromRelease', 'toRelease'])->findOrFail($data['update_id']);

        try {
            $rollback = $this->fleetService->dispatchRollback($targetUpdate, $request->user()?->id);
            if ($rollback->status === \App\Models\ThemeFleetUpdate::STATUS_FAILED) {
                return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                    ->with('error', "عملیات بازگشت به نسخه قبل برای «{$targetUpdate->site?->title}» ناموفق بود: " . $rollback->failure_reason);
            }

            return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                ->with('success', "سایت «{$targetUpdate->site?->title}» با موفقیت به اسنپ‌شات و نسخه قبل بازگردانی شد.");
        } catch (\Throwable $e) {
            return redirect()->route('admin.onboarding.create', ['tab' => 'fleet-updates'])
                ->with('error', "خطا در عملیات بازگشت به نسخه قبل: " . $e->getMessage());
        }
    }
}
