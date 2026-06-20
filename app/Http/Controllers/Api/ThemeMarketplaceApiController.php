<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemePurchaseOrder;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API فروشگاه قالب — برای customer-site (auth: tenant.api / license_key + domain)
 */
class ThemeMarketplaceApiController extends Controller
{
    public function __construct(
        protected PaymentGatewayResolver $gatewayResolver
    ) {}

    // GET /api/theme-marketplace
    public function index(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $themes = Theme::with('marketplaceMeta')
            ->marketplace()
            ->orderByDesc(fn ($q) => $q->from('theme_marketplace')->whereColumn('theme_id', 'themes.id')->select('is_featured'))
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => $this->formatTheme($t, $site->id));

        return response()->json(['success' => true, 'themes' => $themes->values()->all()]);
    }

    // GET /api/theme-marketplace/{theme}
    public function show(Request $request, Theme $theme): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (!$theme->is_marketplace || !$theme->isPublished()) {
            return response()->json(['success' => false, 'error' => 'theme_not_found'], 404);
        }

        return response()->json([
            'success' => true,
            'theme'   => $this->formatTheme($theme->load('marketplaceMeta'), $site->id),
        ]);
    }

    // GET /api/theme-marketplace/my-licenses
    public function myLicenses(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $licenses = ThemeLicense::with('theme.marketplaceMeta')
            ->where('site_id', $site->id)
            ->orderByDesc('purchased_at')
            ->get()
            ->map(fn ($lic) => [
                'license_key'             => $lic->license_key,
                'purchased_at'            => $lic->purchased_at?->toDateString(),
                'is_permanent'            => $lic->is_permanent,
                'installed_theme_version' => $lic->installed_theme_version,
                'is_outdated'             => $lic->isOutdated(),
                'theme' => $lic->theme ? [
                    'id'            => $lic->theme->id,
                    'name'          => $lic->theme->name,
                    'slug'          => $lic->theme->slug,
                    'thumbnail'     => $lic->theme->thumbnail,
                    'category'      => $lic->theme->category,
                    'version'       => $lic->theme->version ?? 1,
                    'version_notes' => $lic->theme->marketplaceMeta?->version_notes,
                ] : null,
            ]);

        return response()->json(['success' => true, 'licenses' => $licenses->values()->all()]);
    }

    // POST /api/theme-marketplace/{theme}/initiate-purchase
    public function initiatePurchase(Request $request, Theme $theme): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (!$theme->is_marketplace || !$theme->isPublished()) {
            return response()->json(['success' => false, 'error' => 'theme_not_available'], 404);
        }

        if ($theme->hasLicenseForSite($site->id)) {
            return response()->json(['success' => false, 'error' => 'already_purchased'], 422);
        }

        $data = $request->validate([
            'redirect_back_url' => 'required|string|url|max:500',
        ]);

        if ($theme->marketplace_price === 0) {
            $lic = ThemeLicense::grant($site->id, $theme->id);
            return response()->json([
                'success'     => true,
                'free'        => true,
                'license_key' => $lic->license_key,
            ]);
        }

        // Pending order already exists?
        $existing = ThemePurchaseOrder::where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->where('status', ThemePurchaseOrder::STATUS_PENDING)
            ->latest()
            ->first();

        if ($existing && $existing->created_at->gt(now()->subMinutes(30))) {
            return $this->buildPaymentResponse($existing);
        }

        $order = ThemePurchaseOrder::create([
            'site_id'           => $site->id,
            'theme_id'          => $theme->id,
            'amount_rial'       => $theme->marketplace_price,
            'status'            => ThemePurchaseOrder::STATUS_PENDING,
            'redirect_back_url' => $data['redirect_back_url'],
        ]);

        try {
            $gateway    = $this->gatewayResolver->resolveForSite($site->id);
            $callbackUrl = route('theme-marketplace.payment-callback', ['order' => $order->id]);
            $desc        = 'خرید قالب: ' . $theme->name;

            $result = $gateway->request($theme->marketplace_price, $callbackUrl, $desc, (string) $order->id);

            if (empty($result['success'])) {
                $order->update(['status' => ThemePurchaseOrder::STATUS_FAILED]);
                return response()->json(['success' => false, 'error' => $result['error'] ?? 'gateway_error'], 502);
            }

            $order->update([
                'gateway'           => $gateway->name(),
                'gateway_authority' => $result['authority'] ?? null,
            ]);

            return response()->json([
                'success'      => true,
                'free'         => false,
                'redirect_url' => $result['redirect_url'],
                'order_id'     => $order->id,
            ]);

        } catch (\Throwable $e) {
            $order->update(['status' => ThemePurchaseOrder::STATUS_FAILED]);
            return response()->json(['success' => false, 'error' => 'gateway_exception'], 502);
        }
    }

    // GET /api/theme-marketplace/{theme}/download
    public function download(Request $request, Theme $theme): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (!$theme->hasLicenseForSite($site->id)) {
            return response()->json(['success' => false, 'error' => 'no_license'], 403);
        }

        // Record install: increment installs_count, update installed_theme_version on license
        $theme->increment('installs_count');
        ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)
            ->update(['installed_theme_version' => $theme->version ?? 1]);
        // Auto-promote to bestseller
        if ($theme->installs_count >= 10) {
            \App\Models\ThemeMarketplace::where('theme_id', $theme->id)
                ->where('is_bestseller', false)
                ->update(['is_bestseller' => true, 'is_new_release' => false]);
        }

        return response()->json([
            'success'      => true,
            'theme_id'     => $theme->id,
            'theme_name'   => $theme->name,
            'theme_slug'   => $theme->slug,
            'header_blocks'=> $theme->header_blocks ?? [],
            'footer_blocks'=> $theme->footer_blocks ?? [],
            'pages'        => $theme->pages ?? [],
            'global_styles'=> $theme->global_styles ?? [],
            'settings'     => $theme->settings ?? [],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function formatTheme(Theme $theme, int $siteId): array
    {
        $meta = $theme->marketplaceMeta;

        return [
            // Core
            'id'             => $theme->id,
            'name'           => $theme->name,
            'slug'           => $theme->slug,
            'description'    => $meta?->marketplace_description ?? $theme->description,
            'thumbnail'      => $theme->thumbnail,
            'preview_image'  => $theme->preview_image,
            // Pricing (Sprint 10)
            'price_rial'     => $theme->marketplace_price,
            'annual_price'   => $meta?->annual_price,
            'lifetime_price' => $meta?->lifetime_price,
            'is_free'        => $theme->marketplace_price === 0,
            'license_type'   => $theme->license_type ?? 'free',
            // Taxonomy (Sprint 10)
            'category'       => $theme->category,
            // Badges (Sprint 10)
            'is_featured'    => (bool) $meta?->is_featured,
            'is_bestseller'  => (bool) $meta?->is_bestseller,
            'is_new_release' => (bool) $meta?->is_new_release,
            'installs_count' => (int) $theme->installs_count,
            // Quality (Sprint 10)
            'quality_score'  => $meta?->getOverallScore() ?? 0,
            'quality_badge'  => $meta?->getScoreBadge() ?? null,
            'preview_url'    => $meta?->preview_url,
            // Meta
            'screenshots'    => $meta?->screenshots ?? [],
            'requirements'   => $meta?->requirements ?? [],
            'version_notes'  => $meta?->version_notes,
            'sales_count'    => $meta?->sales_count ?? 0,
            'has_license'    => ThemeLicense::where('site_id', $siteId)->where('theme_id', $theme->id)->exists(),
        ];
    }

    private function buildPaymentResponse(ThemePurchaseOrder $order): JsonResponse
    {
        // Rebuild redirect_url from stored authority if gateway supports it
        $gateway    = $this->gatewayResolver->resolveForSite($order->site_id);
        $callbackUrl = route('theme-marketplace.payment-callback', ['order' => $order->id]);
        $result = $gateway->request($order->amount_rial, $callbackUrl, 'خرید قالب', (string) $order->id);

        if (empty($result['success'])) {
            return response()->json(['success' => false, 'error' => 'gateway_error'], 502);
        }

        $order->update([
            'gateway_authority' => $result['authority'] ?? $order->gateway_authority,
        ]);

        return response()->json([
            'success'      => true,
            'free'         => false,
            'redirect_url' => $result['redirect_url'],
            'order_id'     => $order->id,
        ]);
    }
}
