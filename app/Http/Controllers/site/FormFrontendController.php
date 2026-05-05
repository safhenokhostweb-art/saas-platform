<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Site;
use App\Models\FormSubmission;
use App\Services\CaptchaService;
use Illuminate\Http\Request;

class FormFrontendController extends Controller
{
    // لیست فرم‌های فعال برای یک سایت
    public function siteForms(Site $site)
    {
        $forms = $site->forms()->wherePivot('is_active', true)->get();

        return view('site.forms.by_site', compact('site', 'forms'));
    }

    // نمایش یک فرم مشخص برای یک سایت
    public function showForSite(Site $site, string $slug)
    {
        $form = $site->forms()
            ->where('slug', $slug)
            ->wherePivot('is_active', true)
            ->with('fields')
            ->firstOrFail();

        $captcha = ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'persian_code' => '', 'normal_text' => ''];
        if (CaptchaService::isModuleEnabled()) {
            $cfg = CaptchaService::getForSite($site->id);
            if ($cfg['enabled']) {
                $captcha['enabled'] = true;
                $captcha['type'] = $cfg['type'];
                $captcha['hcaptcha_site_key'] = $cfg['hcaptcha_site_key'];
                if ($cfg['type'] === CaptchaService::TYPE_PERSIAN_NUMBER) {
                    $captcha['persian_code'] = CaptchaService::generatePersianNumber(4);
                } elseif ($cfg['type'] === CaptchaService::TYPE_NORMAL) {
                    $gen = CaptchaService::generateNormal();
                    $captcha['normal_text'] = $gen['text'];
                }
            }
        }

        return view('site.forms.show', compact('site', 'form', 'captcha'));
    }

    // ثبت اطلاعات فرم
    public function submitForSite(Request $request, Site $site, string $slug)
    {
        $form = $site->forms()
            ->where('slug', $slug)
            ->wherePivot('is_active', true)
            ->with('fields')
            ->firstOrFail();

        $rules = [];
        foreach ($form->fields as $field) {
            $rules['field_' . $field->id] = $field->is_required ? 'required' : 'nullable';
        }

        $captchaValid = true;
        if (CaptchaService::isModuleEnabled()) {
            $cfg = CaptchaService::getForSite($site->id);
            if ($cfg['enabled']) {
                if ($cfg['type'] === CaptchaService::TYPE_PERSIAN_NUMBER) {
                    $captchaValid = CaptchaService::validatePersianNumber($request->input('captcha_answer', ''));
                } elseif ($cfg['type'] === CaptchaService::TYPE_NORMAL) {
                    $captchaValid = CaptchaService::validateNormal($request->input('captcha_answer', ''));
                } elseif ($cfg['type'] === CaptchaService::TYPE_HCAPTCHA) {
                    $captchaValid = CaptchaService::verifyHcaptcha($request->input('h-captcha-response', ''), $cfg['hcaptcha_secret_key']);
                }
                if (!$captchaValid) {
                    return back()->withInput()->withErrors(['captcha' => 'کپچا اشتباه است یا منقضی شده. لطفا دوباره تلاش کنید.']);
                }
            }
        }

        $validated = $request->validate($rules);

        FormSubmission::create([
            'form_id' => $form->id,
            'site_id' => $site->id,
            'data' => $validated,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'فرم با موفقیت ارسال شد ✅');
    }
}
