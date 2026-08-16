<?php
namespace App\Http\Controllers\Api;

use App\Helpers\PlatformSettings;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\ModuleEcosystem\Contracts\ModuleEntitlementResolverContract;
use App\Services\DesignSystemService;
use App\Services\ExpiryReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * API تنظیمات سایت برای customer-site: نام سایت، ماژول‌های فعال، انقضای پشتیبانی، و UX (رنگ، فونت، لوگو).
 */
class SiteConfigController extends Controller
{
    public function __construct(
        protected SiteContextContract $siteContext,
        protected ModuleEntitlementResolverContract $entitlements,
    ) {}

    /**
     * GET /api/site-config?license_key=...&domain=...
     */
    public function show(Request $request): JsonResponse
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost() ?: config('app.url'), '/');
        $siteId = $this->siteContext->requireSiteId();
        $site = Site::query()->findOrFail($siteId);
        $modules = $this->entitlements->projectionForSite($site);
        $designSystem = app(DesignSystemService::class);
        $theme = $designSystem->resolveTheme($siteId);
        $font = $designSystem->resolveFont($siteId);
        $logoPath = PlatformSettings::logoUrl();
        if ($siteId && \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            $overrides = \App\Models\SiteSetting::allForSite($siteId);
            if (!empty($overrides['site_logo'])) {
                $logoPath = $overrides['site_logo'];
            }
        }

        $logoUrl = $logoPath ? (preg_match('#^https?://#i', $logoPath) ? $logoPath : $baseUrl . $logoPath) : null;

        $notifications = [];
        $debug         = [];
        // Lazy-load site برای backward-compat هشدارهای انقضای قدیمی
        $siteModel     = null;
        if ($siteId && \Illuminate\Support\Facades\Schema::hasTable('platform_site_notifications')) {
            $totalForSite = \App\Models\PlatformSiteNotification::where('site_id', $siteId)->count();
            $activeForSite = \App\Models\PlatformSiteNotification::where('site_id', $siteId)->active()->count();
            if ($request->boolean('debug')) {
                $all = \App\Models\PlatformSiteNotification::where('site_id', $siteId)->get();
                $debug['notifications'] = [
                    'site_id' => $siteId,
                    'total_for_site' => $totalForSite,
                    'active_for_site' => $activeForSite,
                    'items' => $all->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->title,
                        'is_active' => $n->is_active,
                        'starts_at' => $n->starts_at?->toIso8601String(),
                        'expires_at' => $n->expires_at?->toIso8601String(),
                        'audience' => $n->audience,
                        'priority' => $n->priority ?? 'normal',
                    ])->toArray(),
                ];
            }
            $items = \App\Models\PlatformSiteNotification::where('site_id', $siteId)
                ->active()
                ->orderByRaw("CASE COALESCE(priority, 'normal') WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 3 END")
                ->orderBy('order')
                ->orderByDesc('id')
                ->get();
            foreach ($items as $n) {
                $imgUrl = null;
                if ($n->image_path) {
                    if (preg_match('#^https?://#i', $n->image_path)) {
                        $imgUrl = $n->image_path;
                    } else {
                        $path = preg_replace('#^storage/+#', '', ltrim((string) $n->image_path, '/'));
                        $imgUrl = $baseUrl . '/storage/' . $path;
                    }
                }

                $body     = (string) $n->body;
                $link     = $n->link;
                $linkText = $n->link_text;

                // هشدارهای انقضا: source of truth = Site.{field}; تاریخ پویا محاسبه می‌شود
                // فرمت marker داخلی: __expiry__:{type}:{YYYY-MM-DD}
                if ($link && str_starts_with((string) $link, '__expiry__:')) {
                    $parts       = explode(':', (string) $link, 3);
                    $renewalLink = '/admin/inbox';
                    $renewalText = 'تمدید هاست';
                    if (count($parts) === 3) {
                        $expiryType = $parts[1];
                        $fieldMap   = [
                            'host'    => ['field' => 'host_expires_at',    'label' => 'هاست',      'link_text' => 'تمدید هاست'],
                            'domain'  => ['field' => 'domain_expires_at',  'label' => 'دامنه',     'link_text' => 'تمدید دامنه'],
                            'support' => ['field' => 'support_expires_at', 'label' => 'پشتیبانی', 'link_text' => 'تمدید پشتیبانی'],
                        ];
                        try {
                            $siteModel  ??= \App\Models\Site::find($siteId);
                            $typeMeta    = $fieldMap[$expiryType] ?? null;
                            $actualDate  = $typeMeta && $siteModel ? $siteModel->{$typeMeta['field']} : null;
                            // منبع حقیقت: Site.{field} — اگر موجود نباشد از stored date استفاده می‌شود
                            $expiryDate  = $actualDate
                                ? \Carbon\Carbon::instance($actualDate)
                                : \Carbon\Carbon::parse($parts[2]);
                            $daysLeft    = max(0, (int) now()->diffInDays($expiryDate, false));

                            // تمدید شده (> ۳۰ روز): notification را غیرفعال کن و از لیست حذف کن
                            if ($daysLeft > ExpiryReminderService::DAYS_WINDOW) {
                                $n->updateQuietly(['is_active' => false]);
                                continue;
                            }

                            $daysLeftFa  = \App\Helpers\NumberHelper::toPersianNumbers((string) $daysLeft);
                            $labelFa     = $typeMeta['label'] ?? mb_substr(trim((string) $n->title), mb_strlen('هشدار انقضای '));
                            $dateFa      = \App\Helpers\Jalali::formatFa($expiryDate, 'Y/m/d');
                            $body        = "تاریخ انقضای {$labelFa}: {$dateFa} (حدود {$daysLeftFa} روز مانده). لطفاً برای تمدید اقدام کنید.";
                            $renewalText = $typeMeta['link_text'] ?? $renewalText;

                            // اگر تاریخ در DB قدیمی شده، آن را sync کن
                            if ($actualDate && $parts[2] !== $expiryDate->toDateString()) {
                                $n->updateQuietly([
                                    'body' => "تاریخ انقضای {$labelFa}: {$dateFa}. لطفاً برای تمدید اقدام کنید.",
                                    'link' => '__expiry__:' . $expiryType . ':' . $expiryDate->toDateString(),
                                ]);
                            }
                        } catch (\Throwable) {}
                    }
                    // marker داخلی است؛ لینک تمدید به‌جای آن ارسال می‌شود
                    $link     = $renewalLink;
                    $linkText = $renewalText;
                } elseif (str_starts_with((string) $n->title, 'هشدار انقضای ')) {
                    // Backward-compat: notification قدیمی (بدون marker) — تاریخ واقعی را از Site بخوان
                    $siteModel    ??= \App\Models\Site::find($siteId);
                    $renewalLink   = '/admin/inbox';
                    $renewalText   = 'تمدید هاست';
                    if ($siteModel) {
                        $titleTrimmed = trim((string) $n->title);
                        $labelMap     = [
                            'هشدار انقضای هاست'     => ['field' => 'host_expires_at',    'type' => 'host',    'label' => 'هاست',      'link_text' => 'تمدید هاست'],
                            'هشدار انقضای دامنه'     => ['field' => 'domain_expires_at',  'type' => 'domain',  'label' => 'دامنه',     'link_text' => 'تمدید دامنه'],
                            'هشدار انقضای پشتیبانی' => ['field' => 'support_expires_at', 'type' => 'support', 'label' => 'پشتیبانی', 'link_text' => 'تمدید پشتیبانی'],
                        ];
                        $meta = $labelMap[$titleTrimmed] ?? null;
                        if ($meta && $siteModel->{$meta['field']}) {
                            try {
                                $expiryDate = \Carbon\Carbon::instance($siteModel->{$meta['field']});
                                $daysLeft   = max(0, (int) now()->diffInDays($expiryDate, false));

                                // تمدید شده (> ۳۰ روز): غیرفعال و حذف از لیست
                                if ($daysLeft > ExpiryReminderService::DAYS_WINDOW) {
                                    $n->updateQuietly(['is_active' => false]);
                                    $link     = null;
                                    $linkText = null;
                                    continue;
                                }

                                $daysLeftFa = \App\Helpers\NumberHelper::toPersianNumbers((string) $daysLeft);
                                $expiryLink = '__expiry__:' . $meta['type'] . ':' . $expiryDate->toDateString();
                                $labelFa    = $meta['label'];
                                $dateFa     = \App\Helpers\Jalali::formatFa($expiryDate, 'Y/m/d');
                                // Upgrade notification به فرمت جدید با marker
                                $n->updateQuietly([
                                    'body' => "تاریخ انقضای {$labelFa}: {$dateFa}. لطفاً برای تمدید اقدام کنید.",
                                    'link' => $expiryLink,
                                ]);
                                $body        = "تاریخ انقضای {$labelFa}: {$dateFa} (حدود {$daysLeftFa} روز مانده). لطفاً برای تمدید اقدام کنید.";
                                $renewalText = $meta['link_text'];
                            } catch (\Throwable) {}
                        }
                    }
                    $link     = $renewalLink;
                    $linkText = $renewalText;
                }

                $notifications[] = [
                    'id'             => $n->id,
                    'title'          => $n->title,
                    'body'           => $body,
                    'type'           => $n->type,
                    'link'           => $link,
                    'link_text'      => $linkText,
                    'image_url'      => $imgUrl,
                    'position'       => $n->position,
                    'image_position' => $n->image_position,
                    'audience'       => $n->audience ?? 'public',
                    'priority'       => $n->priority ?? 'normal',
                    'created_at'     => $n->created_at?->toIso8601String(),
                ];
            }
        }

        $menus = [];
        if ($siteId && \Illuminate\Support\Facades\Schema::hasTable('menus') && in_array('menu', $modules, true)) {
            $menuList = \App\Models\Menu::where('site_id', $siteId)
                ->with(['items' => function ($q) {
                    $q->where('is_active', true)
                        ->with(['children' => fn ($q2) => $q2->where('is_active', true)->orderBy('order')])
                        ->orderBy('order');
                }])
                ->orderBy('order')
                ->get();
            foreach ($menuList as $menu) {
                $items = [];
                foreach ($menu->items as $item) {
                    $items[] = $this->menuItemToArray($item, $baseUrl);
                }
                $menus[$menu->location] = [
                    'name' => $menu->name,
                    'items' => $items,
                ];
            }
        }

        $seo = [];
        if ($siteId && \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            $all = \App\Models\SiteSetting::allForSite($siteId);
            foreach (\App\Models\SiteSetting::SEO_KEYS as $key) {
                if (isset($all[$key]) && (string) $all[$key] !== '') {
                    $seo[$key] = $all[$key];
                }
            }
            if (!empty($seo['seo_og_image']) && !preg_match('#^https?://#i', $seo['seo_og_image'])) {
                $seo['seo_og_image'] = $baseUrl . '/' . ltrim($seo['seo_og_image'], '/');
            }
        }

        // Resolve assigned ThemeProfile for this tenant site
        $themePresetSlug = null;
        if ($siteId && \Illuminate\Support\Facades\Schema::hasTable('theme_profiles') && \Illuminate\Support\Facades\Schema::hasTable('tenant_theme_overrides')) {
            $override = \App\Models\TenantThemeOverride::forSite($siteId);
            $themePresetSlug = $override?->preset_key;
        }

        $payload = [
            'allowed'            => true,
            'theme_preset_slug'  => $themePresetSlug,
            'site_title'         => $site->title,
            'modules' => $modules,
            'menus' => $menus,
            'notifications' => $notifications,
            'notifications_count' => count($notifications),
            'support_expires_at' => $site->support_expires_at?->toIso8601String(),
            'support_starts_at' => $site->support_starts_at?->toIso8601String(),
            'support_level' => $site->support_level ?? 'basic',
            'seo' => $seo,
            'ux' => [
                'general' => [
                    'platform_name' => PlatformSettings::platformName(),
                    'logo_url' => $logoUrl,
                    'registration_open' => PlatformSettings::isRegistrationOpen(),
                ],
                'theme' => $theme,
                'font' => $font,
                'css_variables' => $designSystem->getCssVariablesFromThemeFont($theme, $font),
                'font_imports' => $designSystem->getFontImportsFromFont($font),
                'guide_widget' => PlatformSettings::allGuideWidget(),
                'admin_notif_widget' => [
                    'position' => PlatformSettings::adminNotifWidgetPosition(),
                    'position_classes' => PlatformSettings::adminNotifWidgetPositionClasses(),
                ],
            ],
            'theme_builder' => $this->themeBuilderPayload($siteId),
        ];
        if ($request->boolean('debug')) {
            $payload['debug'] = array_merge($debug, [
                'site_id' => $siteId,
            ]);
        }
        return response()->json($payload);
    }

    /**
     * تبدیل یک آیتم منو (و فرزندان) به آرایه برای API.
     */
    private function menuItemToArray(\App\Models\MenuItem $item, string $baseUrl): array
    {
        $url = $item->url;
        if ($url && !preg_match('#^https?://#i', $url) && !str_starts_with($url, '#')) {
            $url = $baseUrl . '/' . ltrim($url, '/');
        }
        $out = [
            'title' => $item->title,
            'url' => $url ?: null,
            'target' => $item->target ?? '_self',
        ];
        if ($item->relationLoaded('children') && $item->children->isNotEmpty()) {
            $out['children'] = $item->children->map(fn ($c) => $this->menuItemToArray($c, $baseUrl))->values()->all();
        }
        return $out;
    }

    /**
     * Theme Builder: header, footer, global_styles for customer-site.
     */
    private function themeBuilderPayload(?int $siteId): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('themes')) {
            return ['header_blocks' => [], 'footer_blocks' => [], 'global_styles' => [], 'settings' => []];
        }
        $service = app(\App\Services\ThemeBuilder\ThemeBuilderService::class);
        $payload = $service->payloadForSite($siteId);
        $engine = app(\App\Services\ThemeBuilder\GlobalStyleEngine::class);
        $payload['css_block'] = $engine->renderCssBlock($payload['global_styles'], $siteId);
        return $payload;
    }
}
