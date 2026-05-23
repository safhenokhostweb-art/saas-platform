<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use App\Models\Site;
use App\Services\CaptchaService;
use Illuminate\Http\Request;

class FormFrontendController extends Controller
{
    /**
     * لیست فرم‌های فعال (عمومی)
     */
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

        $captcha = ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'persian_code' => '', 'normal_text' => ''];
        if (CaptchaService::isModuleEnabled()) {
            $cfg = CaptchaService::getForSite(null);
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

        $captchaValid = true;
        if (CaptchaService::isModuleEnabled()) {
            $cfg = CaptchaService::getForSite(null);
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

    /**
     * لیست فرم‌های فعال برای یک سایت (multi-site)
     */
    public function siteForms(Site $site)
    {
        $forms = $site->forms()->wherePivot('is_active', true)->get();

        return view('forms::site.forms.index', compact('site', 'forms'));
    }

    /**
     * نمایش یک فرم مشخص برای یک سایت (multi-site)
     */
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

        return view('forms::site.forms.show', compact('site', 'form', 'captcha'));
    }

    /**
     * ثبت اطلاعات فرم برای یک سایت (multi-site)
     */
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
