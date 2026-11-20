<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\ActivityLogger;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\Domains\SiteDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 3 — the site's "دامنه" panel: DNS check, SSL issue/renew, and
 * replacing the domain with the customer's own. Transport only; the rules
 * live in SiteDomainService and the remote work in SiteDomainProvisioner.
 */
class SiteDomainController extends Controller
{
    public function __construct(
        private readonly SiteDomainService $domains,
        private readonly DomainDnsPreflight $preflight,
    ) {}

    public function show(Site $site): View
    {
        $primary = $this->domains->primary($site);

        return view('admin.sites.domain', [
            'site'         => $site,
            'primary'      => $primary,
            'pending'      => $site->siteDomains()->where('role', SiteDomain::ROLE_PENDING)->first(),
            'aliases'      => $site->siteDomains()->where('role', SiteDomain::ROLE_ALIAS)->orderBy('domain')->get(),
            'installation' => $this->domains->installationFor($site),
            'serverIp'     => $this->domains->serverIp($site),
            'platformManaged' => $this->preflight->isPlatformManaged($primary->domain),
            'wwwFor'       => fn (string $domain) => $this->preflight->wwwAlias($domain),
        ]);
    }

    public function check(Site $site, SiteDomain $siteDomain): RedirectResponse
    {
        $report = $this->domains->checkDns($siteDomain);
        $apex = $report['records'][0] ?? null;

        return back()->with($apex && $apex['ok'] ? 'success' : 'warning', $apex && $apex['ok']
            ? "DNS دامنهٔ «{$siteDomain->domain}» به سرور سایت متصل است."
            : "DNS دامنهٔ «{$siteDomain->domain}» هنوز به سرور سایت ({$report['server_ip']}) اشاره نمی‌کند. جزئیات را در جدول ببینید.");
    }

    public function ssl(Site $site, SiteDomain $siteDomain, Request $request): RedirectResponse
    {
        $this->domains->queue($siteDomain, SiteDomainService::ACTION_SSL);
        ActivityLogger::log('site.domain_ssl_requested', "درخواست صدور/تمدید SSL برای {$siteDomain->domain}", $site, ['site_domain_id' => $siteDomain->id]);

        return back()->with('success', "بررسی DNS، پیکربندی Nginx و صدور SSL برای «{$siteDomain->domain}» در صف قرار گرفت. نتیجه چند دقیقهٔ دیگر در همین صفحه نمایش داده می‌شود.");
    }

    public function store(Site $site, Request $request): RedirectResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:253']]);
        $row = $this->domains->addPending($site, $data['domain']);
        ActivityLogger::log('site.domain_pending_added', "دامنهٔ «{$row->domain}» برای سایت «{$site->title}» ثبت شد", $site, ['site_domain_id' => $row->id]);

        return back()->with('success', "دامنهٔ «{$row->domain}» ثبت شد. پس از تنظیم رکوردهای DNS، «اتصال و جایگزینی دامنه» را بزنید.");
    }

    public function activate(Site $site, SiteDomain $siteDomain): RedirectResponse
    {
        $this->domains->queue($siteDomain, SiteDomainService::ACTION_ACTIVATE);
        ActivityLogger::log('site.domain_activation_requested', "درخواست جایگزینی دامنهٔ سایت با {$siteDomain->domain}", $site, ['site_domain_id' => $siteDomain->id]);

        return back()->with('success', "اتصال «{$siteDomain->domain}» در صف قرار گرفت: بررسی DNS، صدور SSL و سپس جایگزینی دامنه. تا پایان کار، دامنهٔ فعلی بدون تغییر فعال است.");
    }

    public function destroy(Site $site, SiteDomain $siteDomain): RedirectResponse
    {
        $this->domains->removePending($siteDomain);

        return back()->with('success', "دامنهٔ در انتظار «{$siteDomain->domain}» حذف شد.");
    }
}
