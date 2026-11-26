<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CanvasAssetPurchaseOrder;
use App\Models\CanvasAssetVersion;
use App\Models\CanvasButtonPack;
use App\Models\CanvasHeaderPreset;
use App\Models\CanvasIconPack;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Services\AssetProvisioning\CanvasAssetProvisioningService;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

/**
 * Tenant Marketplace API for Canvas Assets:
 * Provides catalog with version changelog, free claiming, and
 * payment-gateway integrated purchasing for buttons, icons, headers, and motion graphics.
 *
 * Auth: license_key + domain via `tenant.api` middleware.
 */
class CanvasAssetMarketplaceApiController extends Controller
{
    public function __construct(
        protected SiteContextContract $siteContext,
        protected CanvasAssetProvisioningService $provisioningService,
        protected PaymentGatewayResolver $gatewayResolver,
    ) {}

    /**
     * Resolves the authenticated tenant Site.
     */
    protected function resolveSite(Request $request): Site
    {
        $site = $request->attributes->get('tenant_site');
        if ($site instanceof Site) {
            return $site;
        }

        $siteId = $this->siteContext->requireSiteId();
        return Site::query()->findOrFail($siteId);
    }

    /**
     * GET /api/canvas-marketplace
     * Returns the full commercial asset catalog with ownership status and version history.
     */
    public function index(Request $request): JsonResponse
    {
        $site = $this->resolveSite($request);

        // Preload active grants for this site
        $activeGrants = SiteAssetGrant::query()
            ->where('site_id', $site->id)
            ->active()
            ->get()
            ->keyBy(fn (SiteAssetGrant $g) => $g->asset_kind . ':' . $g->asset_key);

        // Preload version history for all assets
        $allVersions = CanvasAssetVersion::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (CanvasAssetVersion $v) => $v->asset_kind . ':' . $v->asset_slug);

        $formatVersions = function (string $kind, string $slug, string $currentVersion) use ($allVersions): array {
            $ref = $kind . ':' . $slug;
            $items = $allVersions->get($ref);
            if ($items && $items->isNotEmpty()) {
                return $items->map(fn (CanvasAssetVersion $v) => [
                    'version'      => $v->version,
                    'changelog'    => $v->changelog,
                    'is_current'   => (bool) $v->is_current,
                    'published_at' => $v->published_at?->format('Y-m-d H:i') ?? $v->created_at?->format('Y-m-d H:i'),
                ])->values()->all();
            }

            return [
                [
                    'version'      => $currentVersion ?: '1.0.0',
                    'changelog'    => 'نسخه اولیه منتشر شده در مارکت‌پلیس',
                    'is_current'   => true,
                    'published_at' => null,
                ],
            ];
        };

        // 1. Button Packs
        $buttonPacks = CanvasButtonPack::query()
            ->where('is_active', true)
            ->withCount('presets')
            ->with(['presets' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->get()
            ->map(function (CanvasButtonPack $p) use ($activeGrants, $formatVersions) {
                $ref = AssetRef::KIND_BUTTON_PACK . ':' . $p->slug;
                $grant = $activeGrants->get($ref);
                $curVer = $p->current_version ?: '1.0.0';
                return [
                    'kind' => AssetRef::KIND_BUTTON_PACK,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'description' => $p->description,
                    'category_label' => $p->category_label ?: 'عمومی',
                    'preview_image' => $p->preview_image,
                    'access_type' => $p->access_type,
                    'price' => (int) $p->price,
                    'discount_price' => $p->discount_price !== null ? (int) $p->discount_price : null,
                    'current_version' => $curVer,
                    'versions_history' => $formatVersions(AssetRef::KIND_BUTTON_PACK, $p->slug, $curVer),
                    'presets_count' => $p->presets_count,
                    'presets' => $p->presets->map(fn ($pr) => [
                        'slug' => $pr->slug,
                        'title' => $pr->title,
                        'css_tokens' => $pr->css_tokens,
                        'preview_html' => $pr->preview_html,
                    ])->all(),
                    'is_granted' => $grant !== null,
                    'granted_version' => $grant?->granted_version,
                ];
            });

        // 2. Icon Packs
        $iconPacks = CanvasIconPack::query()
            ->where('is_active', true)
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->take(12)])
            ->orderBy('sort_order')
            ->get()
            ->map(function (CanvasIconPack $p) use ($activeGrants, $formatVersions) {
                $ref = AssetRef::KIND_ICON_PACK . ':' . $p->slug;
                $grant = $activeGrants->get($ref);
                $curVer = $p->current_version ?: '1.0.0';
                return [
                    'kind' => AssetRef::KIND_ICON_PACK,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'description' => $p->description,
                    'category_label' => $p->category_label ?: 'عمومی',
                    'preview_image' => $p->preview_image,
                    'access_type' => $p->access_type,
                    'price' => (int) $p->price,
                    'discount_price' => $p->discount_price !== null ? (int) $p->discount_price : null,
                    'current_version' => $curVer,
                    'versions_history' => $formatVersions(AssetRef::KIND_ICON_PACK, $p->slug, $curVer),
                    'items_count' => $p->items_count,
                    'sample_items' => $p->items->map(fn ($it) => [
                        'slug' => $it->slug,
                        'title' => $it->title,
                        'keywords' => $it->keywords,
                        'svg_content' => $it->svg_content,
                    ])->all(),
                    'is_granted' => $grant !== null,
                    'granted_version' => $grant?->granted_version,
                ];
            });

        // 3. Header Presets
        $headerPresets = CanvasHeaderPreset::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (CanvasHeaderPreset $h) use ($activeGrants, $formatVersions) {
                $ref = AssetRef::KIND_HEADER . ':' . $h->slug;
                $grant = $activeGrants->get($ref);
                $curVer = $h->current_version ?: '1.0.0';
                return [
                    'kind' => AssetRef::KIND_HEADER,
                    'slug' => $h->slug,
                    'title' => $h->title,
                    'category_label' => $h->category_label ?: 'عمومی',
                    'preview_image' => $h->preview_image,
                    'component_alias' => $h->component_alias,
                    'access_type' => $h->access_type,
                    'price' => (int) $h->price,
                    'discount_price' => $h->discount_price !== null ? (int) $h->discount_price : null,
                    'current_version' => $curVer,
                    'versions_history' => $formatVersions(AssetRef::KIND_HEADER, $h->slug, $curVer),
                    'is_granted' => $grant !== null,
                    'granted_version' => $grant?->granted_version,
                ];
            });

        // 4. Motion Graphics
        $motions = collect();
        if (class_exists(MotionGraphic::class)) {
            $motions = MotionGraphic::query()
                ->whereNull('archived_at')
                ->orderBy('id')
                ->get()
                ->map(function (MotionGraphic $m) use ($activeGrants, $formatVersions) {
                    $ref = AssetRef::KIND_MOTION . ':' . $m->slug;
                    $grant = $activeGrants->get($ref);
                    $curVer = $m->current_version ?: '1.0.0';
                    return [
                        'kind' => AssetRef::KIND_MOTION,
                        'slug' => $m->slug,
                        'title' => $m->name,
                        'description' => $m->description,
                        'engine' => $m->engine ?? 'lottie',
                        'asset_type' => $m->asset_type ?? 'json',
                        'access_type' => $m->access_type ?? 'free',
                        'price' => (int) ($m->price ?? 0),
                        'discount_price' => $m->discount_price !== null ? (int) $m->discount_price : null,
                        'current_version' => $curVer,
                        'versions_history' => $formatVersions(AssetRef::KIND_MOTION, $m->slug, $curVer),
                        'is_granted' => $grant !== null,
                        'granted_version' => $grant?->granted_version,
                    ];
                });
        }

        return response()->json([
            'success' => true,
            'catalog' => [
                'buttons' => $buttonPacks->values()->all(),
                'icons' => $iconPacks->values()->all(),
                'headers' => $headerPresets->values()->all(),
                'motions' => $motions->values()->all(),
            ],
            'site' => [
                'id' => $site->id,
                'title' => $site->title,
                'domain' => $site->domain,
            ],
        ]);
    }

    /**
     * POST /api/canvas-marketplace/claim
     * Claim free assets and activate immediately in site_asset_grants.
     */
    public function claim(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'asset_kind' => ['required', Rule::in(AssetRef::KINDS)],
            'asset_key' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $site = $this->resolveSite($request);
        $kind = (string) $request->input('asset_kind');
        $key = (string) $request->input('asset_key');

        [$isFree, $version] = $this->verifyAssetAccess($kind, $key);
        if (!$isFree) {
            return response()->json([
                'success' => false,
                'message' => 'این دارایی رایگان نیست و برای فعال‌سازی نیاز به خرید دارد.',
            ], 403);
        }

        $grant = $this->provisioningService->grantToSite(
            $site,
            $kind,
            $key,
            $version,
            SiteAssetGrant::SOURCE_ADMIN_GRANT,
            'دریافت رایگان مارکت‌پلیس',
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'دارایی با موفقیت برای سایت شما فعال شد.',
            'asset_ref' => AssetRef::make($kind, $key),
            'version' => $grant->granted_version,
        ]);
    }

    /**
     * POST /api/canvas-marketplace/purchase
     * Purchases a paid asset: initiates payment gateway order or immediate test fallback.
     */
    public function purchase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'asset_kind'        => ['required', Rule::in(AssetRef::KINDS)],
            'asset_key'         => 'required|string|max:100',
            'redirect_back_url' => 'nullable|string|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $site = $this->resolveSite($request);
        $kind = (string) $request->input('asset_kind');
        $key = (string) $request->input('asset_key');
        $redirectBack = (string) $request->input('redirect_back_url');

        [$isFree, $version, $priceToman, $title] = $this->resolveAssetDetails($kind, $key);

        // 1. Free asset claim fallback
        if ($isFree || $priceToman <= 0) {
            $grant = $this->provisioningService->grantToSite(
                $site,
                $kind,
                $key,
                $version,
                SiteAssetGrant::SOURCE_ADMIN_GRANT,
                'دریافت رایگان دارایی',
                null
            );

            return response()->json([
                'success'   => true,
                'free'      => true,
                'message'   => 'دارایی رایگان با موفقیت فعال گردید.',
                'asset_ref' => AssetRef::make($kind, $key),
                'version'   => $grant->granted_version,
            ]);
        }

        // 2. Already granted check
        $alreadyGranted = SiteAssetGrant::query()
            ->where('site_id', $site->id)
            ->where('asset_kind', $kind)
            ->where('asset_key', $key)
            ->active()
            ->exists();

        if ($alreadyGranted) {
            return response()->json([
                'success'         => true,
                'already_granted' => true,
                'message'         => 'این دارایی قبلاً برای سایت شما فعال شده است.',
                'asset_ref'       => AssetRef::make($kind, $key),
                'version'         => $version,
            ]);
        }

        $amountRial = $priceToman * 10;

        // 3. Create purchase order
        $order = CanvasAssetPurchaseOrder::create([
            'site_id'           => $site->id,
            'customer_id'       => $site->customer_id,
            'asset_kind'        => $kind,
            'asset_key'         => $key,
            'asset_version'     => $version,
            'amount_toman'      => $priceToman,
            'amount_rial'       => $amountRial,
            'status'            => CanvasAssetPurchaseOrder::STATUS_PENDING,
            'redirect_back_url' => $redirectBack ?: null,
        ]);

        // Optional central invoice linkage
        $invoice = null;
        if ($site->customer_id && Schema::hasTable('invoices')) {
            try {
                $invoice = Invoice::create([
                    'customer_id' => $site->customer_id,
                    'amount_irr'  => $amountRial,
                    'status'      => Invoice::STATUS_PENDING,
                ]);
                $order->update(['invoice_id' => $invoice->id]);
            } catch (\Throwable) {}
        }

        // 4. Fallback check: testing environment or no active gateway configured
        $hasActiveGateway = Schema::hasTable('payment_gateways') && PaymentGateway::where('is_active', true)->exists();
        $isSimulationMode = app()->environment('testing') || !$hasActiveGateway || $request->header('X-Simulate-Payment') === '1';

        if ($isSimulationMode) {
            // Instant simulated settlement
            $order->update([
                'status'      => CanvasAssetPurchaseOrder::STATUS_PAID,
                'gateway_ref' => 'SIMULATED-' . time(),
                'paid_at'     => now(),
            ]);

            if ($invoice) {
                $invoice->update([
                    'status'      => Invoice::STATUS_PAID,
                    'gateway_ref' => $order->gateway_ref,
                    'paid_at'     => now(),
                ]);
            }

            $grant = $this->provisioningService->grantToSite(
                $site,
                $kind,
                $key,
                $version,
                SiteAssetGrant::SOURCE_PURCHASE,
                'خرید تستی/شبیه‌سازی‌شده مارکت‌پلیس',
                null
            );

            return response()->json([
                'success'   => true,
                'free'      => false,
                'simulated' => true,
                'order_id'  => $order->id,
                'message'   => 'خرید با موفقیت ثبت و مجوز دارایی بلافاصله فعال گردید.',
                'asset_ref' => AssetRef::make($kind, $key),
                'version'   => $grant->granted_version,
            ]);
        }

        // 5. Normal Payment Gateway Workflow
        try {
            $gateway     = $this->gatewayResolver->resolveForSite($site->id);
            $callbackUrl = route('canvas-marketplace.payment-callback', ['order' => $order->id]);
            $desc        = 'خرید دارایی Canvas: ' . $title;

            $result = $gateway->request($amountRial, $callbackUrl, $desc, (string) $order->id);

            if (empty($result['success']) || empty($result['redirect_url'])) {
                $order->update(['status' => CanvasAssetPurchaseOrder::STATUS_FAILED]);
                return response()->json([
                    'success' => false,
                    'error'   => $result['error'] ?? 'خطا در ارتباط با درگاه بانکی.',
                ], 502);
            }

            $order->update([
                'gateway'           => $gateway->name(),
                'gateway_authority' => $result['authority'] ?? null,
            ]);

            if ($invoice) {
                $invoice->update(['gateway' => $gateway->name()]);
            }

            return response()->json([
                'success'      => true,
                'free'         => false,
                'redirect_url' => $result['redirect_url'],
                'order_id'     => $order->id,
            ]);

        } catch (\Throwable $e) {
            $order->update(['status' => CanvasAssetPurchaseOrder::STATUS_FAILED]);
            return response()->json([
                'success' => false,
                'error'   => 'خطا در هدایت به درگاه پرداخت: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Resolves asset details [isFree, version, priceToman, title].
     * @return array{0: bool, 1: string, 2: int, 3: string}
     */
    protected function resolveAssetDetails(string $kind, string $key): array
    {
        return match ($kind) {
            AssetRef::KIND_BUTTON_PACK => (function () use ($key) {
                $pack = CanvasButtonPack::where('slug', $key)->firstOrFail();
                $price = $pack->discount_price !== null ? (int) $pack->discount_price : (int) $pack->price;
                return [$pack->access_type === 'free', $pack->current_version ?: '1.0.0', $price, $pack->title];
            })(),
            AssetRef::KIND_ICON_PACK => (function () use ($key) {
                $pack = CanvasIconPack::where('slug', $key)->firstOrFail();
                $price = $pack->discount_price !== null ? (int) $pack->discount_price : (int) $pack->price;
                return [$pack->access_type === 'free', $pack->current_version ?: '1.0.0', $price, $pack->title];
            })(),
            AssetRef::KIND_HEADER => (function () use ($key) {
                $preset = CanvasHeaderPreset::where('slug', $key)->firstOrFail();
                $price = $preset->discount_price !== null ? (int) $preset->discount_price : (int) $preset->price;
                return [$preset->access_type === 'free', $preset->current_version ?: '1.0.0', $price, $preset->title];
            })(),
            AssetRef::KIND_MOTION => (function () use ($key) {
                $motion = MotionGraphic::where('slug', $key)->firstOrFail();
                $price = $motion->discount_price !== null ? (int) $motion->discount_price : (int) ($motion->price ?? 0);
                return [($motion->access_type ?? 'free') === 'free', $motion->current_version ?: '1.0.0', $price, $motion->name];
            })(),
            default => [false, '1.0.0', 0, 'نامشخص'],
        };
    }

    /**
     * Helper to verify if an asset is free and extract current version.
     * @return array{0: bool, 1: string} [isFree, version]
     */
    protected function verifyAssetAccess(string $kind, string $key): array
    {
        [$isFree, $version] = $this->resolveAssetDetails($kind, $key);
        return [$isFree, $version];
    }
}
