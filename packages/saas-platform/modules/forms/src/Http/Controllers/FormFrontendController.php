<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\CaptchaServiceInterface;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;

class FormFrontendController extends Controller
{
    public function __construct(
        protected CaptchaServiceInterface $captchaService,
        protected \SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface $siteRepository
    ) {}

    public function index()
    {
        $forms = Form::where('is_active', true)->orderBy('title')->get();

        return view('forms::site.forms.index', compact('forms'));
    }

    /**
     * نمایش یک فرم مشخص (عمومی)
     */
    public function show(string $slug)
    {
        $form = Form::where('slug', $slug)
            ->where('is_active', true)
            ->with(['fields' => fn ($q) => $q->where('is_active', true)->orderBy('position')])
            ->firstOrFail();

        $captcha = $this->buildCaptcha(null);

        return view('forms::site.forms.show', compact('form', 'captcha'));
    }

    /**
     * ثبت اطلاعات فرم (عمومی)
     */
    public function submit(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        $rules = [];
        foreach ($form->fields as $field) {
            $key = 'field_'.$field->id;
            if ($field->type === 'checkbox') {
                $rules[$key] = $field->is_required ? 'required|array|min:1' : 'nullable|array';
            } else {
                $rules[$key] = $field->is_required ? 'required' : 'nullable';
            }
        }

        $captchaValid = $this->validateCaptcha($request, null);
        if ($captchaValid !== true) {
            return back()->withInput()->withErrors(['captcha' => 'کپچا اشتباه است یا منقضی شده. لطفا دوباره تلاش کنید.']);
        }

        $validated = $request->validate($rules);

        $payload = [];
        foreach ($form->fields as $field) {
            $key = 'field_'.$field->id;
            if (!array_key_exists($key, $validated)) {
                continue;
            }
            $value = $validated[$key];
            if ($field->type === 'checkbox' && is_array($value)) {
                $value = implode('، ', array_map('strval', $value));
            }
            $payload[$key] = $value;
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => $payload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'فرم با موفقیت ارسال شد ✅');
    }

    public function siteForms(int $site)
    {
        $siteModel = $this->siteRepository->find($site);
        abort_unless($siteModel, 404);

        $forms = \SaaSPlatform\Modules\Forms\Models\Form::whereHas(
            'sites',
            fn ($q) => $q->where('sites.id', $siteModel->getId())->wherePivot('is_active', true)
        )->get();

        return view('forms::site.forms.index', compact('siteModel', 'forms'));
    }

    public function showForSite(int $site, string $slug)
    {
        $siteModel = $this->siteRepository->find($site);
        abort_unless($siteModel, 404);

        $form = \SaaSPlatform\Modules\Forms\Models\Form::where('slug', $slug)
            ->whereHas('sites', fn ($q) => $q->where('sites.id', $siteModel->getId())->wherePivot('is_active', true))
            ->with('fields')
            ->firstOrFail();

        $captcha = $this->buildCaptcha($siteModel->getId());

        return view('forms::site.forms.show', compact('siteModel', 'form', 'captcha'));
    }

    public function submitForSite(Request $request, int $site, string $slug)
    {
        $siteModel = $this->siteRepository->find($site);
        abort_unless($siteModel, 404);

        $form = \SaaSPlatform\Modules\Forms\Models\Form::where('slug', $slug)
            ->whereHas('sites', fn ($q) => $q->where('sites.id', $siteModel->getId())->wherePivot('is_active', true))
            ->with('fields')
            ->firstOrFail();

        $rules = [];
        foreach ($form->fields as $field) {
            $rules['field_' . $field->id] = $field->is_required ? 'required' : 'nullable';
        }

        $captchaValid = $this->validateCaptcha($request, $siteModel->getId());
        if ($captchaValid !== true) {
            return back()->withInput()->withErrors(['captcha' => 'کپچا اشتباه است یا منقضی شده. لطفا دوباره تلاش کنید.']);
        }

        $validated = $request->validate($rules);

        FormSubmission::create([
            'form_id' => $form->id,
            'site_id' => $siteModel->getId(),
            'data'    => $validated,
            'ip'      => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'فرم با موفقیت ارسال شد ✅');
    }

    private function buildCaptcha(?int $siteId): array
    {
        $captcha = ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'persian_code' => '', 'normal_text' => ''];

        if (!$this->captchaService->isEnabled()) {
            return $captcha;
        }

        $cfg = $this->captchaService->getConfigForSite($siteId);
        if (!$cfg['enabled']) {
            return $captcha;
        }

        $captcha['enabled']          = true;
        $captcha['type']             = $cfg['type'];
        $captcha['hcaptcha_site_key'] = $cfg['hcaptcha_site_key'];

        if ($cfg['type'] === CaptchaServiceInterface::TYPE_PERSIAN_NUMBER) {
            $captcha['persian_code'] = $this->captchaService->generatePersianNumber(4);
        } elseif ($cfg['type'] === CaptchaServiceInterface::TYPE_NORMAL) {
            $gen = $this->captchaService->generateNormal();
            $captcha['normal_text'] = $gen['text'];
        }

        return $captcha;
    }

    private function validateCaptcha(Request $request, ?int $siteId): bool
    {
        if (!$this->captchaService->isEnabled()) {
            return true;
        }
        $cfg = $this->captchaService->getConfigForSite($siteId);
        if (!$cfg['enabled']) {
            return true;
        }
        return match ($cfg['type']) {
            CaptchaServiceInterface::TYPE_PERSIAN_NUMBER =>
                $this->captchaService->validatePersianNumber($request->input('captcha_answer', '')),
            CaptchaServiceInterface::TYPE_NORMAL =>
                $this->captchaService->validateNormal($request->input('captcha_answer', '')),
            CaptchaServiceInterface::TYPE_HCAPTCHA =>
                $this->captchaService->verifyHcaptcha($request->input('h-captcha-response', ''), $cfg['hcaptcha_secret_key']),
            default => true,
        };
    }
}
