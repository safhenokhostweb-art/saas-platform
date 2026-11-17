<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartOnboardingRequest;
use App\Models\CustomerInstallation;
use App\Models\Theme;
use App\Services\ActivityLogger;
use App\Services\Onboarding\OnboardingDomainPolicy;
use App\Services\Onboarding\SiteOnboardingService;
use App\Services\Onboarding\SiteTemplateBinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ADR-088 — the unified customer & site onboarding wizard. Replaces the
 * customers/create, sites/create, sites/wizard and customer-deployment
 * wizard entry points (they redirect here). Transport only: every decision
 * is SiteOnboardingService's.
 */
class OnboardingWizardController extends Controller
{
    public function __construct(
        private readonly SiteOnboardingService $onboarding,
        private readonly OnboardingDomainPolicy $domains,
    ) {}

    public function create(Request $request): View
    {
        $options = $this->onboarding->wizardOptions();
        $prefill = $request->filled('mobile') ? $this->onboarding->lookupCustomer((string) $request->query('mobile')) : null;

        return view('admin.onboarding.wizard', compact('options', 'prefill'));
    }

    public function lookupCustomer(Request $request): JsonResponse
    {
        $request->validate(['mobile' => 'required|string|max:20']);

        return response()->json($this->onboarding->lookupCustomer((string) $request->query('mobile')));
    }

    public function checkDomain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode'   => ['required', Rule::in([OnboardingDomainPolicy::MODE_SUBDOMAIN, OnboardingDomainPolicy::MODE_CUSTOM])],
            'domain' => ['required', 'string', 'max:253'],
        ]);

        return response()->json($this->domains->check($data['mode'], $data['domain']));
    }

    public function store(StartOnboardingRequest $request): RedirectResponse
    {
        $installation = $this->onboarding->start($request->validated(), $request->user());

        return redirect()
            ->route('admin.infrastructure.customer-deployment.installations.show', $installation)
            ->with('success', "راه‌اندازی «{$installation->customer_domain}» آغاز شد. همهٔ مراحل — از سرور و دامنه تا لایسنس و قالب — به‌صورت خودکار اجرا می‌شوند.");
    }

    /** Re-run the theme binding of a completed onboarding (assign + push). */
    public function retryTemplate(CustomerInstallation $installation, SiteTemplateBinder $binder): RedirectResponse
    {
        $themeId = $binder->binding($installation)['theme_id'] ?? null;
        $theme = $themeId ? Theme::find($themeId) : null;
        if (! $installation->isComplete() || $theme === null) {
            return back()->with('error', 'این نصب قالبی برای اعمال ندارد یا هنوز کامل نشده است.');
        }

        $binder->markPendingPush($installation, $theme);
        $binder->dispatch($installation);

        ActivityLogger::log('onboarding.template_retry', "تلاش دوباره برای اعمال قالب روی {$installation->customer_domain}", $installation, [
            'installation_id' => $installation->id,
        ]);

        return back()->with('success', 'اعمال قالب دوباره در صف قرار گرفت.');
    }
}
