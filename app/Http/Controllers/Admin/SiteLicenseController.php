<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Jalali;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\LicenseServerService;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SiteLicenseController extends Controller
{
    /**
     * فرم صدور لایسنس برای سایت
     */
    public function create(Site $site)
    {
        $site->load('customer');
        return view('admin.sites.license-create', compact('site'));
    }

    /**
     * صدور لایسنس و نمایش یک‌بارهٔ کلید
     */
    public function store(Request $request, Site $site, LicenseServerService $licenseService)
    {
        $request->validate([
            'domain' => 'nullable|string|max:255',
            'expires_at' => 'nullable|string|max:20',
        ]);

        $rawDomain = $request->filled('domain') ? $request->domain : $site->domain;
        $domain = $rawDomain ? $this->normalizeDomain($rawDomain) : null;
        $expiresAt = $request->filled('expires_at') ? Jalali::toCarbon($request->expires_at) : null;

        $key = $licenseService->issue($site, $domain, $expiresAt, 'site_template');

        ActivityLogger::log('license.issued', 'صدور لایسنس برای سایت: ' . $site->title, $site);

        return redirect()
            ->route('admin.sites.license.show', ['site' => $site->id])
            ->with('issued_license_key', $key);
    }

    /**
     * نمایش یک‌بارهٔ کلید لایسنس (بعد از صدور)
     */
    public function show(Site $site)
    {
        $key = session('issued_license_key');
        if (!$key) {
            return redirect()->route('admin.sites.index')
                ->with('info', 'لایسنس قبلاً صادر شده است. برای صدور مجدد از منوی سایت اقدام کنید.');
        }
        session()->forget('issued_license_key');
        return view('admin.sites.license-show', compact('site', 'key'));
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain);
            $domain = $parsed['host'] ?? $domain;
        }
        $domain = rtrim($domain, '/');
        return strtolower($domain);
    }
}
