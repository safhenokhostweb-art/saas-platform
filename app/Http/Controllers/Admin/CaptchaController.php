<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Site;
use App\Models\SiteCaptchaSetting;
use App\Models\Module;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CaptchaController extends Controller
{
    public function index()
    {
        $module = Module::where('slug', 'captcha')->first();
        $moduleEnabled = $module && $module->is_active;

        $captchaEnabled = (string) Setting::get('captcha_enabled', '0') === '1';
        $authCaptchaEnabled = (string) Setting::get('auth_captcha_enabled', '0') === '1';
        $captchaType = (string) Setting::get('captcha_type', CaptchaService::TYPE_PERSIAN_NUMBER);
        $hcaptchaSiteKey = (string) Setting::get('hcaptcha_site_key', '');
        $hcaptchaSecretKey = (string) Setting::get('hcaptcha_secret_key', '');

        return view('admin.captcha.index', compact(
            'moduleEnabled',
            'captchaEnabled',
            'authCaptchaEnabled',
            'captchaType',
            'hcaptchaSiteKey',
            'hcaptchaSecretKey'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'captcha_enabled' => 'nullable|boolean',
            'auth_captcha_enabled' => 'nullable|boolean',
            'captcha_type' => 'required|in:persian_number,normal,hcaptcha',
            'hcaptcha_site_key' => 'nullable|string|max:500',
            'hcaptcha_secret_key' => 'nullable|string|max:500',
        ]);

        $enabled = $request->boolean('captcha_enabled');
        $authEnabled = $request->boolean('auth_captcha_enabled');
        Setting::updateOrCreate(['key' => 'captcha_enabled'], ['value' => $enabled ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'auth_captcha_enabled'], ['value' => $authEnabled ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'captcha_type'], ['value' => $request->captcha_type]);
        Setting::updateOrCreate(['key' => 'hcaptcha_site_key'], ['value' => $request->input('hcaptcha_site_key', '')]);
        Setting::updateOrCreate(['key' => 'hcaptcha_secret_key'], ['value' => $request->input('hcaptcha_secret_key', '')]);

        return back()->with('success', 'تنظیمات کپچا ذخیره شد.');
    }

    /** تنظیمات کپچا برای یک سایت (پنل ادمین اصلی یا مشتری) */
    public function siteSettings(Site $site)
    {
        if (!Schema::hasTable('site_captcha_settings')) {
            return redirect()->route('admin.captcha.index')
                ->with('error', 'جدول site_captcha_settings وجود ندارد. در ترمینال دستور php artisan migrate را اجرا کنید.');
        }
        $setting = $site->captchaSetting ?? new SiteCaptchaSetting(['site_id' => $site->id]);
        $platformEnabled = (string) Setting::get('captcha_enabled', '0') === '1';
        $platformType = (string) Setting::get('captcha_type', CaptchaService::TYPE_PERSIAN_NUMBER);
        $platformSiteKey = (string) Setting::get('hcaptcha_site_key', '');
        $platformSecretKey = (string) Setting::get('hcaptcha_secret_key', '');

        return view('admin.captcha.site', compact('site', 'setting', 'platformEnabled', 'platformType', 'platformSiteKey', 'platformSecretKey'));
    }

    public function siteUpdate(Request $request, Site $site)
    {
        if (!Schema::hasTable('site_captcha_settings')) {
            return redirect()->route('admin.captcha.index')
                ->with('error', 'جدول site_captcha_settings وجود ندارد. در ترمینال دستور php artisan migrate را اجرا کنید.');
        }
        $request->validate([
            'captcha_enabled' => 'nullable|boolean',
            'captcha_type' => 'required|in:persian_number,normal,hcaptcha',
            'hcaptcha_site_key' => 'nullable|string|max:500',
            'hcaptcha_secret_key' => 'nullable|string|max:500',
        ]);

        SiteCaptchaSetting::updateOrCreate(
            ['site_id' => $site->id],
            [
                'captcha_enabled' => $request->boolean('captcha_enabled'),
                'captcha_type' => $request->captcha_type,
                'hcaptcha_site_key' => $request->input('hcaptcha_site_key', ''),
                'hcaptcha_secret_key' => $request->input('hcaptcha_secret_key', ''),
            ]
        );

        return back()->with('success', 'تنظیمات کپچا برای این سایت ذخیره شد.');
    }
}
