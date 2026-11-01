<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemeMarketplace;
use App\Models\ThemePurchaseOrder;
use App\Models\Site;
use App\Models\ThemeAssignment;
use App\Services\PublishedThemeAssignmentService;
use App\Services\ThemeBuilder\ThemeDemoDataService;
use App\Services\ThemeBuilder\ThemeMarketplaceQualityService;
use Illuminate\Http\Request;
use DomainException;

class ThemeMarketplaceAdminController extends Controller
{
    public function __construct(
        private ThemeMarketplaceQualityService $quality,
        private ThemeDemoDataService $demoData,
        private PublishedThemeAssignmentService $assignments,
    ) {}

    public function index(Request $request)
    {
        $query = Theme::with('marketplaceMeta')
            ->whereNull('site_id')
            ->orderByDesc('is_marketplace')
            ->orderBy('name');

        // Search filter (GAP-005)
        if ($search = $request->input('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        // Category filter (GAP-006)
        if ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }

        $themes = $query->get();

        $categories    = Theme::whereNull('site_id')->whereNotNull('category')->distinct()->pluck('category');
        $totalLicenses = ThemeLicense::count();
        $totalRevenue  = ThemePurchaseOrder::where('status', ThemePurchaseOrder::STATUS_PAID)->sum('amount_rial');
        $pendingOrders = ThemePurchaseOrder::where('status', ThemePurchaseOrder::STATUS_PENDING)->count();

        return view('admin.theme-marketplace.index', compact(
            'themes', 'categories', 'totalLicenses', 'totalRevenue', 'pendingOrders'
        ));
    }

    // GAP-013: Theme marketplace preview page
    public function preview(Theme $theme)
    {
        $meta     = ThemeMarketplace::where('theme_id', $theme->id)->first();
        $manifest = $this->demoData->getManifest($theme);
        return view('admin.theme-marketplace.preview', compact('theme', 'meta', 'manifest'));
    }

    // GAP-012: Build/refresh demo data manifest for a theme
    public function buildDemoManifest(Theme $theme)
    {
        $manifest = $this->demoData->saveManifest($theme);
        return response()->json([
            'success'       => true,
            'has_demo_data' => count($manifest['detected_content']) > 0,
            'total_items'   => $manifest['total_items'],
            'content'       => $manifest['detected_content'],
        ]);
    }

    // GAP-004: Quality scoring dashboard
    public function scoring()
    {
        $themes = Theme::with('marketplaceMeta')
            ->whereNull('site_id')
            ->where('is_marketplace', true)
            ->orderBy('name')
            ->get();

        return view('admin.theme-marketplace.scoring', compact('themes'));
    }

    // GAP-003 + BRK-001: Score a single theme via HTTP (AJAX)
    public function scoreTheme(Theme $theme)
    {
        $result = $this->quality->scoreAndPersist($theme);
        $meta   = ThemeMarketplace::where('theme_id', $theme->id)->first();

        return response()->json([
            'success'       => true,
            'overall'       => $meta?->getOverallScore() ?? 0,
            'badge'         => $meta?->getScoreBadge() ?? 'needs-work',
            'verdict'       => $result['verdict'],
            'design'        => $result['design_score'],
            'commerce'      => $result['commerce_score'],
            'performance'   => $result['performance_score'],
            'completeness'  => $result['completeness_score'],
            'marketplace'   => $result['marketplace_score'],
            'issues'        => $result['quality_issues'] ?? [],
        ]);
    }

    // BRK-002: Increment installs_count (called by customer-site after install)
    public function recordInstall(Request $request, Theme $theme)
    {
        $request->validate(['site_id' => 'nullable|exists:sites,id']);

        $theme->increment('installs_count');

        // Auto-promote to bestseller when 10+ installs
        if ($theme->installs_count >= 10) {
            $meta = ThemeMarketplace::where('theme_id', $theme->id)->first();
            if ($meta && !$meta->is_bestseller) {
                $meta->update([
                    'is_bestseller'      => true,
                    'is_new_release'     => false,
                ]);
                $theme->update(['marketplace_badge' => 'bestseller']);
            }
        }

        return response()->json(['success' => true, 'installs_count' => $theme->installs_count]);
    }

    public function updateListing(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'is_marketplace'         => 'required|boolean',
            'marketplace_price'      => 'required_if:is_marketplace,true|integer|min:0',
            'annual_price'           => 'nullable|integer|min:0',
            'lifetime_price'         => 'nullable|integer|min:0',
            'marketplace_description'=> 'nullable|string|max:2000',
            'screenshots'            => 'nullable|array|max:6',
            'screenshots.*'          => 'nullable|string|max:500',
            'requirements'           => 'nullable|array',
            'requirements.*'         => 'nullable|string|max:100',
            'version_notes'          => 'nullable|string|max:500',
            'preview_url'            => 'nullable|url|max:500',
            'is_featured'            => 'nullable|boolean',
            'is_new_release'         => 'nullable|boolean',
            'is_bestseller'          => 'nullable|boolean',
            'category'               => 'nullable|string|max:60',
            'license_type'           => 'nullable|string|in:free,premium,bundle',
        ]);

        $theme->update([
            'is_marketplace'    => $data['is_marketplace'],
            'marketplace_price' => $data['marketplace_price'] ?? 0,
            'category'          => $data['category'] ?? $theme->category,
            'license_type'      => $data['license_type'] ?? $theme->license_type,
        ]);

        if ($data['is_marketplace']) {
            ThemeMarketplace::updateOrCreate(
                ['theme_id' => $theme->id],
                [
                    'marketplace_description' => $data['marketplace_description'] ?? null,
                    'screenshots'             => array_filter($data['screenshots'] ?? []),
                    'requirements'            => array_filter($data['requirements'] ?? []),
                    'version_notes'           => $data['version_notes'] ?? null,
                    'preview_url'             => $data['preview_url'] ?? null,
                    'annual_price'            => $data['annual_price'] ?? null,
                    'lifetime_price'          => $data['lifetime_price'] ?? null,
                    'is_featured'             => $data['is_featured'] ?? false,
                    'is_new_release'          => $data['is_new_release'] ?? false,
                    'is_bestseller'           => $data['is_bestseller'] ?? false,
                ]
            );
        } else {
            ThemeMarketplace::where('theme_id', $theme->id)->delete();
        }

        return back()->with('success', 'اطلاعات مارکت‌پلیس ذخیره شد.');
    }

    public function toggleFeatured(Theme $theme)
    {
        $meta = ThemeMarketplace::where('theme_id', $theme->id)->first();
        if (!$meta) {
            return back()->with('error', 'قالب در مارکت‌پلیس نیست.');
        }

        $meta->update(['is_featured' => !$meta->is_featured]);

        return back()->with('success', 'وضعیت ویژه تغییر کرد.');
    }

    public function licenses()
    {
        $licenses = ThemeLicense::with(['theme', 'site', 'licensedRelease', 'assignment.platformLicense'])
            ->orderByDesc('purchased_at')
            ->paginate(30);

        $orders = ThemePurchaseOrder::with(['theme', 'site'])
            ->whereIn('status', [ThemePurchaseOrder::STATUS_PAID, ThemePurchaseOrder::STATUS_PENDING])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.theme-marketplace.licenses', compact('licenses', 'orders'));
    }

    public function revokeLicense(ThemeLicense $license)
    {
        $this->assignments->revoke($license);

        return back()->with('success', 'لایسنس لغو شد.');
    }

    public function grantLicense(Request $request)
    {
        $data = $request->validate([
            'site_id'  => 'required|exists:sites,id',
            'theme_id' => 'required|exists:themes,id',
        ]);

        $theme = Theme::findOrFail((int) $data['theme_id']);

        // Phase 2d (ADR-025): even an admin manual/comp grant must not
        // entitle a site to a theme that has no valid current release — the
        // customer would receive a "license" to a theme with nothing
        // commercially approved behind it. This is a commercial-entitlement
        // action regardless of who initiates it, not an authoring/preview
        // action.
        if (!$theme->isCommerciallyEligible()) {
            return back()->with('error', 'این قالب در حال حاضر واجد شرایط تجاری نیست (بدون نسخه انتشار معتبر یا گزارش کیفیت موفق) و نمی‌توان برای آن لایسنس صادر کرد.');
        }

        try {
            $result = $this->assignments->assign(
                Site::findOrFail((int) $data['site_id']),
                $theme,
                ThemeAssignment::SOURCE_ADMIN,
                assignedBy: $request->user()?->id,
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', sprintf(
            'اختصاص انجام شد: Assignment #%d، PlatformLicense #%d، ThemeLicense #%d، Release #%d.',
            $result->assignment->id,
            $result->platformLicense->id,
            $result->themeLicense->id,
            $result->release->id,
        ));
    }
}
