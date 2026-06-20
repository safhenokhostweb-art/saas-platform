<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeBundle;
use App\Models\ThemeBundleOrder;
use App\Models\ThemeLicense;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeBundleApiController extends Controller
{
    public function __construct(protected PaymentGatewayResolver $gatewayResolver) {}

    // GET /api/theme-bundles
    public function index(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $bundles = ThemeBundle::where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => $this->formatBundle($b, $site->id));

        return response()->json(['success' => true, 'bundles' => $bundles->values()->all()]);
    }

    // GET /api/theme-bundles/{bundle}
    public function show(Request $request, ThemeBundle $themeBundle): JsonResponse
    {
        if (!$themeBundle->is_active) {
            return response()->json(['success' => false, 'error' => 'bundle_not_found'], 404);
        }

        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        return response()->json(['success' => true, 'bundle' => $this->formatBundle($themeBundle, $site->id)]);
    }

    // POST /api/theme-bundles/{bundle}/purchase
    public function purchase(Request $request, ThemeBundle $themeBundle): JsonResponse
    {
        if (!$themeBundle->is_active) {
            return response()->json(['success' => false, 'error' => 'bundle_not_available'], 404);
        }

        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if ($themeBundle->hasPurchasedBySite($site->id)) {
            return response()->json(['success' => false, 'error' => 'already_purchased'], 422);
        }

        $data = $request->validate([
            'redirect_back_url' => 'required|string|url|max:500',
        ]);

        // Free bundle — grant immediately
        if ($themeBundle->price === 0) {
            ThemeBundleOrder::create([
                'site_id'   => $site->id,
                'bundle_id' => $themeBundle->id,
                'amount_rial' => 0,
                'status'    => ThemeBundleOrder::STATUS_PAID,
            ]);
            $count = $themeBundle->grantLicensesToSite($site->id);
            return response()->json([
                'success' => true,
                'free'    => true,
                'licenses_granted' => $count,
            ]);
        }

        $order = ThemeBundleOrder::create([
            'site_id'           => $site->id,
            'bundle_id'         => $themeBundle->id,
            'amount_rial'       => $themeBundle->price,
            'status'            => ThemeBundleOrder::STATUS_PENDING,
            'redirect_back_url' => $data['redirect_back_url'],
        ]);

        try {
            $gateway    = $this->gatewayResolver->resolveForSite($site->id);
            $callbackUrl = route('theme-bundles.payment-callback', ['order' => $order->id]);
            $result = $gateway->request($themeBundle->price, $callbackUrl, 'خرید باندل: ' . $themeBundle->name, (string) $order->id);

            if (empty($result['success'])) {
                $order->update(['status' => ThemeBundleOrder::STATUS_FAILED]);
                return response()->json(['success' => false, 'error' => 'gateway_error'], 502);
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

        } catch (\Throwable) {
            $order->update(['status' => ThemeBundleOrder::STATUS_FAILED]);
            return response()->json(['success' => false, 'error' => 'gateway_exception'], 502);
        }
    }

    private function formatBundle(ThemeBundle $bundle, int $siteId): array
    {
        $themes = Theme::whereNull('site_id')
            ->whereIn('id', $bundle->theme_ids ?? [])
            ->get(['id', 'name', 'slug', 'thumbnail', 'marketplace_price', 'category']);

        $originalTotal = $themes->sum('marketplace_price');
        $savings       = max(0, $originalTotal - $bundle->price);

        return [
            'id'             => $bundle->id,
            'name'           => $bundle->name,
            'slug'           => $bundle->slug,
            'description'    => $bundle->description,
            'thumbnail'      => $bundle->thumbnail,
            'price'          => $bundle->price,
            'is_free'        => $bundle->price === 0,
            'is_featured'    => $bundle->is_featured,
            'original_total' => $originalTotal,
            'savings'        => $savings,
            'discount_pct'   => $originalTotal > 0 ? (int) round(($savings / $originalTotal) * 100) : 0,
            'theme_count'    => $themes->count(),
            'themes'         => $themes->map(fn ($t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'slug'      => $t->slug,
                'thumbnail' => $t->thumbnail,
                'price'     => $t->marketplace_price,
                'category'  => $t->category,
            ])->values()->all(),
            'has_purchased'  => $bundle->hasPurchasedBySite($siteId),
            // Check if all individual themes are already licensed
            'all_licensed'   => $themes->every(fn ($t) => ThemeLicense::where('site_id', $siteId)->where('theme_id', $t->id)->exists()),
        ];
    }
}
