<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionGraphicsAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * Phase 7 (docs/motion-admin-management/): the first — and, per the
 * task's "no public API"-turned-"minimal internal admin interface"
 * scope, only — HTTP surface for Motion Graphics. Platform-Admin-only
 * (the `admin` route middleware, same as every other route in this
 * controller group), never reachable by a Customer.
 *
 * Every Internal-Test-Site-scoped action explicitly sets
 * TenantContextContract to the selected Site before delegating to
 * MotionGraphicsAdminService — see that class's docblock for why this
 * is required (the feature flag and every fail-closed check are always
 * evaluated against a specific site's tenant context, never a global
 * "admin mode"). This is the same TenantContext::set() the platform
 * already exposes; nothing new is introduced here.
 */
class MotionGraphicsAdminController extends Controller
{
    public function __construct(
        private readonly MotionGraphicsAdminService $adminService,
        private readonly TenantContextContract $tenantContext,
        private readonly SiteContextContract $siteContext,
    ) {}

    /**
     * Internal Test Site picker — the entry point. Deliberately not
     * gated behind Motion's own authorization (it touches no Motion
     * data, only the platform's own Site list), so it works even for a
     * site that has never been enabled, letting the admin discover that
     * from the dashboard's own fail-closed error rather than being
     * silently excluded from the picker.
     */
    public function selectSite(): View
    {
        $sites = Site::query()->orderByDesc('id')->limit(200)->get(['id', 'title', 'domain']);

        return view('admin.motion-graphics.select-site', ['sites' => $sites]);
    }

    public function dashboard(Site $site): View
    {
        $context = $this->contextFor($site);

        try {
            $summary = $this->adminService->dashboardSummary($context);
            $error = null;
        } catch (MotionAdapterFailClosedException $e) {
            $summary = null;
            $error = $e;
        }

        return view('admin.motion-graphics.dashboard', [
            'site' => $site,
            'summary' => $summary,
            'error' => $error,
        ]);
    }

    public function templates(Site $site): View
    {
        $context = $this->contextFor($site);

        try {
            $templates = $this->adminService->listTemplates($context);
            $error = null;
        } catch (MotionAdapterFailClosedException $e) {
            $templates = collect();
            $error = $e;
        }

        return view('admin.motion-graphics.templates', [
            'site' => $site,
            'templates' => $templates,
            'error' => $error,
            'preview' => null,
        ]);
    }

    public function previewCompatibility(Request $request, Site $site): View
    {
        $context = $this->contextFor($site);
        $data = $this->validateAssignmentRequest($request);

        $templates = collect();
        $error = null;
        $preview = null;

        try {
            $templates = $this->adminService->listTemplates($context);
            $preview = $this->adminService->previewCompatibility($data['required'], $data['compatibility_rules'], $context);
        } catch (MotionAdapterFailClosedException $e) {
            $error = $e;
        }

        return view('admin.motion-graphics.templates', [
            'site' => $site,
            'templates' => $templates,
            'error' => $error,
            'preview' => $preview,
        ]);
    }

    public function assignPackage(Request $request, Site $site, ThemeTemplate $template): RedirectResponse
    {
        $context = $this->contextFor($site);
        $data = $this->validateAssignmentRequest($request);
        $templateReference = $this->templateReference($template);

        try {
            $this->adminService->assignPackageToTemplate(
                $templateReference, $data['required'], $data['optional'], $data['compatibility_rules'], $context
            );

            return redirect()
                ->route('admin.motion-graphics.templates', $site)
                ->with('success', 'بسته موشن به قالب اختصاص یافت.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()
                ->route('admin.motion-graphics.templates', $site)
                ->with('error', 'اختصاص بسته انجام نشد: '.$e->getMessage());
        }
    }

    public function activateSite(Request $request, Site $site, ThemeTemplate $template): RedirectResponse
    {
        $context = $this->contextFor($site);
        $data = $this->validateAssignmentRequest($request);
        $templateReference = $this->templateReference($template);

        try {
            $this->adminService->activateForInternalSite(
                $templateReference, $data['required'], $data['optional'], $data['compatibility_rules'],
                $request->string('brand_variant')->value() ?: null, $context
            );

            return redirect()
                ->route('admin.motion-graphics.preview', $site)
                ->with('success', 'بسته برای سایت آزمایشی داخلی فعال شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()
                ->route('admin.motion-graphics.templates', $site)
                ->with('error', 'فعال‌سازی برای سایت انجام نشد: '.$e->getMessage());
        }
    }

    public function preview(Site $site): View
    {
        $context = $this->contextFor($site);

        try {
            $packages = $this->adminService->listActivatedPackagesForSite($context);
            $error = null;
        } catch (MotionAdapterFailClosedException $e) {
            $packages = collect();
            $error = $e;
        }

        return view('admin.motion-graphics.preview', [
            'site' => $site,
            'packages' => $packages,
            'error' => $error,
            'projections' => null,
            'selectedPackage' => null,
        ]);
    }

    public function previewProjection(Site $site, SiteAssetPackage $sitePackage): View
    {
        $context = $this->contextFor($site);
        $packages = collect();
        $projections = null;
        $error = null;

        try {
            $packages = $this->adminService->listActivatedPackagesForSite($context);
            $projections = $this->adminService->previewRuntimeProjection($sitePackage, $context);
        } catch (MotionAdapterFailClosedException $e) {
            $error = $e;
        }

        return view('admin.motion-graphics.preview', [
            'site' => $site,
            'packages' => $packages,
            'error' => $error,
            'projections' => $projections,
            'selectedPackage' => $sitePackage,
        ]);
    }

    private function contextFor(Site $site): MotionAdapterContext
    {
        $this->tenantContext->set($site);
        $this->siteContext->setSiteId((int) $site->getKey());

        return new MotionAdapterContext(
            actorId: 'user:'.Auth::id(),
            siteId: (string) $site->id,
            correlationId: (string) Str::uuid(),
        );
    }

    private function templateReference(ThemeTemplate $template): string
    {
        return 'theme-template:'.$template->uuid;
    }

    /**
     * @return array{required: list<array{asset_id: string, version?: int}>, optional: list<array{asset_id: string, version?: int}>, compatibility_rules: array<string, mixed>}
     */
    private function validateAssignmentRequest(Request $request): array
    {
        $validated = $request->validate([
            'required_asset_ids' => 'required|string',
            'optional_asset_ids' => 'nullable|string',
            'compatibility_asset_type' => 'nullable|string',
            'compatibility_section' => 'nullable|string',
            'compatibility_industry' => 'nullable|string',
            'compatibility_theme_tokens_required' => 'nullable|boolean',
        ]);

        $toRefs = static fn (string $raw): array => collect(explode(',', $raw))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->map(fn ($id) => ['asset_id' => $id])
            ->values()
            ->all();

        $rules = array_filter([
            'asset_type' => $validated['compatibility_asset_type'] ?? null,
            'section' => $validated['compatibility_section'] ?? null,
            'industry' => $validated['compatibility_industry'] ?? null,
            'theme_tokens_required' => $request->boolean('compatibility_theme_tokens_required') ?: null,
        ], static fn ($v) => $v !== null && $v !== '');

        return [
            'required' => $toRefs($validated['required_asset_ids']),
            'optional' => empty($validated['optional_asset_ids']) ? [] : $toRefs($validated['optional_asset_ids']),
            'compatibility_rules' => $rules,
        ];
    }
}
