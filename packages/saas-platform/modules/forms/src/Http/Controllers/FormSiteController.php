<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;
use SaaSPlatform\Modules\Forms\Http\Requests\FormSiteRequest;
use SaaSPlatform\Modules\Forms\Models\Form;

class FormSiteController extends Controller
{
    public function __construct(
        protected FormSiteServiceInterface $siteService,
        protected SiteRepositoryInterface  $siteRepository
    ) {
        $this->middleware(function ($request, $next) {
            if (app(\SaaSPlatform\Modules\Forms\Services\PlatformContextService::class)->isTenantAdmin()) {
                abort(403, 'دسترسی به این بخش فقط در پلتفرم مرکزی امکان‌پذیر است.');
            }
            return $next($request);
        });
    }

    public function edit(Form $form)
    {
        return view('forms::admin.forms.sites', $this->siteService->getEditData($form));
    }

    public function update(FormSiteRequest $request, Form $form)
    {
        $this->siteService->syncSites($form, $request->input('sites', []));
        return back()->with('success', 'اتصال فرم به سایت‌ها بروزرسانی شد');
    }

    public function toggleSite(Form $form, int $site)
    {
        $siteModel = $this->siteRepository->find($site);
        abort_unless($siteModel, 404);

        $isActive = $this->siteService->toggleSite($form, $siteModel);
        return back()->with('success', $isActive ? 'فرم در این سایت فعال شد.' : 'فرم در این سایت غیرفعال شد.');
    }
}

