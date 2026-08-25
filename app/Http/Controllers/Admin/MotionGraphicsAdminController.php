<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionGraphicsAdminService;
use App\Modules\MotionGraphics\Support\ThemeReference;
use Illuminate\Http\JsonResponse;
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

    /**
     * Theme Builder Motion Picker (authoring): AJAX endpoint backing the
     * live preview card shown as an admin types/pastes an asset id into
     * the required/optional asset fields — GET .../assets/preview?asset_id=.
     * Deliberately JSON-only, admin-only (same `contextFor()`/`admin`
     * middleware as every other action here); returns `{found:false}`
     * rather than a 404 so the picker UI can show "not found" inline
     * without a console error.
     */
    public function previewAsset(Request $request, Site $site): JsonResponse
    {
        $context = $this->contextFor($site);
        $assetId = trim((string) $request->query('asset_id', ''));

        if ($assetId === '') {
            return response()->json(['found' => false]);
        }

        try {
            $preview = $this->adminService->previewAsset($assetId, $context);
        } catch (MotionAdapterFailClosedException $e) {
            return response()->json(['found' => false, 'reason' => $e->failureCode]);
        }

        if ($preview === null) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'asset' => $preview->toArray()]);
    }

    /**
     * Theme Builder Motion Picker (real visual browse): AJAX endpoint
     * backing the "Select Motion" modal — GET .../assets/browse?search=&
     * category=&page=. Returns only the published, global library via
     * MotionGraphicsAdminService::browseLibrary(); a customer-specific/
     * bespoke asset is never returned here, by construction of the
     * underlying MotionAssetLibraryBrowseContract query.
     */
    public function browseAssets(Request $request, Site $site): JsonResponse
    {
        $context = $this->contextFor($site);
        $filters = array_filter([
            'search' => $request->string('search')->trim()->value() ?: null,
            'category' => $request->string('category')->trim()->value() ?: null,
        ], fn ($v) => $v !== null);
        $page = max(1, (int) $request->query('page', 1));

        try {
            $result = $this->adminService->browseLibrary($filters, $page, 24, $context);
        } catch (MotionAdapterFailClosedException $e) {
            return response()->json(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 24, 'reason' => $e->failureCode]);
        }

        return response()->json([
            'items' => array_map(fn ($p) => $p->toArray(), $result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
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

    /**
     * Theme Builder integration: the same assign/activate workflow as
     * templates() above, but for a full marketplace Theme instead of a
     * single ThemeTemplate section — see App\Modules\MotionGraphics\Support\
     * ThemeReference. Reuses MotionGraphicsAdminService unmodified; only the
     * reference-derivation and the model being listed differ.
     */
    public function themes(Site $site): View
    {
        $context = $this->contextFor($site);

        try {
            $themes = Theme::query()->where('is_marketplace', true)->orderByDesc('id')->limit(200)->get();
            $error = null;
        } catch (MotionAdapterFailClosedException $e) {
            $themes = collect();
            $error = $e;
        }

        return view('admin.motion-graphics.themes', [
            'site' => $site,
            'themes' => $themes,
            'error' => $error,
        ]);
    }

    public function assignPackageForTheme(Request $request, Site $site, Theme $theme): RedirectResponse
    {
        $context = $this->contextFor($site);
        $data = $this->validateAssignmentRequest($request);
        $templateReference = ThemeReference::for($theme);

        try {
            $this->adminService->assignPackageToTemplate(
                $templateReference, $data['required'], $data['optional'], $data['compatibility_rules'], $context
            );

            $theme->update(['settings' => array_merge($theme->settings ?? [], [
                'motion' => [
                    'package_reference' => $templateReference,
                    'mode' => 'bundled',
                    'declared_at' => now()->toIso8601String(),
                ],
            ])]);

            return redirect()
                ->route('admin.motion-graphics.themes', $site)
                ->with('success', 'بسته موشن به قالب فروشگاهی اختصاص یافت.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()
                ->route('admin.motion-graphics.themes', $site)
                ->with('error', 'اختصاص بسته انجام نشد: '.$e->getMessage());
        }
    }

    public function activateSiteForTheme(Request $request, Site $site, Theme $theme): RedirectResponse
    {
        $context = $this->contextFor($site);
        $data = $this->validateAssignmentRequest($request);
        $templateReference = ThemeReference::for($theme);

        try {
            $this->adminService->activateForInternalSite(
                $templateReference, $data['required'], $data['optional'], $data['compatibility_rules'],
                $request->string('brand_variant')->value() ?: null, $context
            );

            return redirect()
                ->route('admin.motion-graphics.preview', $site)
                ->with('success', 'بسته برای این سایت فعال شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()
                ->route('admin.motion-graphics.themes', $site)
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
