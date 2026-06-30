<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\Dns\ArvanDnsProvider;
use Illuminate\Http\Request;

class DnsProviderController extends Controller
{
    public function index()
    {
        $setting = DnsProviderSetting::first();
        return view('admin.infrastructure.dns-provider.index', compact('setting'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'provider'    => 'required|in:arvan',
            'root_domain' => 'required|string|max:253',
            'api_token'   => 'nullable|string|max:500',
            'api_base_url'=> 'nullable|url|max:500',
            'cloud_proxy' => 'nullable|boolean',
            'default_ttl' => 'nullable|integer|min:60|max:86400',
            'is_active'   => 'nullable|boolean',
        ]);

        $setting = DnsProviderSetting::firstOrNew([]);

        $setting->provider    = $request->input('provider', 'arvan');
        $setting->root_domain = strtolower(trim($request->input('root_domain'), '. '));
        $setting->api_base_url = $request->filled('api_base_url') ? $request->input('api_base_url') : null;
        $setting->cloud_proxy  = (bool) $request->input('cloud_proxy', false);
        $setting->default_ttl  = (int) ($request->input('default_ttl') ?: 120);
        $setting->is_active    = (bool) $request->input('is_active', false);

        // Only update token if a new value was provided
        if ($request->filled('api_token')) {
            $setting->setToken(trim($request->input('api_token')));
        }

        $setting->save();

        return back()->with('success', 'تنظیمات DNS Provider ذخیره شد.');
    }

    public function healthCheck(Request $request)
    {
        $setting = DnsProviderSetting::first();

        if (!$setting || !$setting->isReady()) {
            return response()->json(['ok' => false, 'message' => 'DNS Provider پیکربندی نشده یا غیرفعال است.']);
        }

        try {
            $provider = new ArvanDnsProvider($setting);
            $result   = $provider->healthCheck();

            $setting->health_status    = $result['ok'] ? 'ok' : 'error';
            $setting->health_message   = $result['message'];
            $setting->health_checked_at = now();
            $setting->saveQuietly();

            return response()->json($result);

        } catch (\Throwable $e) {
            $setting->health_status    = 'error';
            $setting->health_message   = $e->getMessage();
            $setting->health_checked_at = now();
            $setting->saveQuietly();

            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function listRecords(Request $request)
    {
        $setting = DnsProviderSetting::first();

        if (!$setting || !$setting->isReady()) {
            return response()->json(['ok' => false, 'message' => 'DNS Provider پیکربندی نشده.', 'records' => []]);
        }

        try {
            $provider = new ArvanDnsProvider($setting);
            // findARecord with empty string won't list all, so we use direct HTTP call
            $token    = $setting->getDecryptedToken();
            $url      = rtrim($setting->api_base_url ?? 'https://napi.arvancloud.ir/cdn/4.0/domains', '/')
                        . '/' . $setting->root_domain . '/dns-records';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Apikey ' . $token,
                'Accept'        => 'application/json',
            ])->timeout(15)->get($url);

            if ($response->successful()) {
                return response()->json(['ok' => true, 'records' => $response->json('data', [])]);
            }

            return response()->json(['ok' => false, 'message' => 'HTTP ' . $response->status(), 'records' => []]);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'records' => []]);
        }
    }
}
