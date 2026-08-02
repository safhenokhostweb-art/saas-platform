<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SearchConsolePerformanceProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSearchConsoleConnection;
use SaaSPlatform\Modules\SeoAdvanced\Services\GoogleSearchConsoleService;

/**
 * Phase 18-E — Part 2: Google Search Console connect/select-property/
 * disconnect + the performance dashboard. Every action is scoped to a
 * single $siteId route parameter — a site only ever sees/manages its
 * own connection, enforced by explicit where('site_id', $siteId)
 * queries throughout (never a bare Connection::find()).
 */
class SeoAdvancedSearchConsoleController extends Controller
{
    public function show(int $siteId)
    {
        $connection = SeoAdvancedSearchConsoleConnection::where('site_id', $siteId)->first();

        return view('seo-advanced::admin.search-console', compact('siteId', 'connection'));
    }

    public function connect(int $siteId, GoogleSearchConsoleService $service)
    {
        abort_unless(config('seo-advanced.search_console.client_id'), 422, 'ابتدا Client ID/Secret گوگل را در تنظیمات محیط پیکربندی کنید.');

        $redirectUri = route('seo-advanced.admin.search-console.callback', $siteId);

        return redirect()->away($service->buildAuthorizationUrl($siteId, $redirectUri));
    }

    public function callback(Request $request, int $siteId, GoogleSearchConsoleService $service)
    {
        $data = $request->validate(['code' => 'required|string']);
        $redirectUri = route('seo-advanced.admin.search-console.callback', $siteId);

        $service->handleCallback($siteId, $data['code'], $redirectUri);

        return redirect()->route('seo-advanced.admin.search-console', $siteId)->with('success', 'به گوگل متصل شد — اکنون یک Property انتخاب کنید.');
    }

    public function properties(int $siteId, GoogleSearchConsoleService $service)
    {
        $properties = $service->listProperties($siteId);

        return view('seo-advanced::admin.search-console-properties', compact('siteId', 'properties'));
    }

    public function selectProperty(Request $request, int $siteId, GoogleSearchConsoleService $service)
    {
        $data = $request->validate(['property_url' => 'required|string']);
        $service->selectProperty($siteId, $data['property_url']);

        return redirect()->route('seo-advanced.admin.search-console', $siteId)->with('success', 'Property انتخاب شد.');
    }

    public function disconnect(int $siteId, GoogleSearchConsoleService $service)
    {
        $service->disconnect($siteId);

        return redirect()->route('seo-advanced.admin.search-console', $siteId)->with('success', 'اتصال گوگل قطع شد.');
    }

    public function performance(int $siteId, SearchConsolePerformanceProviderInterface $provider)
    {
        $endDate = now()->subDays(2)->toDateString(); // GSC data has a ~2 day lag
        $startDate = now()->subDays(30)->toDateString();

        $performance = $provider->isConnected($siteId)
            ? $provider->getPerformance($siteId, $startDate, $endDate)
            : null;

        return view('seo-advanced::admin.search-console-performance', compact('siteId', 'performance'));
    }
}
