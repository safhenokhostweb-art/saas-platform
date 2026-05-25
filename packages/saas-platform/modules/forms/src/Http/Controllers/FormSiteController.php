<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;
use SaaSPlatform\Modules\Forms\Http\Requests\FormSiteRequest;
use SaaSPlatform\Modules\Forms\Models\Form;
use App\Models\Site;

class FormSiteController extends Controller
{
    public function __construct(
        protected FormSiteServiceInterface $siteService
    ) {
        // PHASE 6.95 — TASK 8: Gate site assignment routes to platform context only.
        // customer-site (tenant) should never access site assignment.
        $this->middleware(function ($request, $next) {
            if (app(\SaaSPlatform\Modules\Forms\Services\PlatformContextService::class)->isTenantAdmin()) {
                abort(403, 'دسترسی به این بخش فقط در پلتفرم مرکزی امکان‌پذیر است.');
            }
            return $next($request);
        });
    }

    public function edit(Form $form)
    {
        $data = $this->siteService->getEditData($form);
        return view('forms::admin.forms.sites', $data);
    }

    public function update(FormSiteRequest $request, Form $form)
    {
        $this->siteService->syncSites($form, $request->input('sites', []));
        return back()->with('success', 'اتصال فرم به سایت‌ها بروزرسانی شد');
    }

    public function toggleSite(Form $form, Site $site)
    {
        $isActive = $this->siteService->toggleSite($form, $site);
        return back()->with('success', $isActive ? 'فرم در این سایت فعال شد.' : 'فرم در این سایت غیرفعال شد.');
    }
}

