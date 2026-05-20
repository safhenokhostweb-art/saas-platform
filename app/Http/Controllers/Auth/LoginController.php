<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\IranianMobile;
use App\Services\ActivityLogger;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * نمایش فرم ورود
     */
    public function show()
    {
        $captcha = ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'persian_code' => '', 'normal_text' => ''];
        $cfg = CaptchaService::getAuthCaptchaConfig();
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
        return view('auth.login', compact('captcha'));
    }

    /**
     * پردازش ورود با موبایل
     */
    public function login(Request $request)
    {
        $cfg = CaptchaService::getAuthCaptchaConfig();
        if ($cfg['enabled']) {
            $captchaValid = false;
            $captchaError = 'کپچا اشتباه است یا منقضی شده. لطفا دوباره تلاش کنید.';
            if ($cfg['type'] === CaptchaService::TYPE_HCAPTCHA) {
                $token = $request->input('h-captcha-response', '');
                if (trim($token) === '') {
                    $captchaError = 'لطفا کپچا را تکمیل کنید.';
                } else {
                    $captchaValid = CaptchaService::verifyHcaptcha($token, $cfg['hcaptcha_secret_key']);
                    if (!$captchaValid) {
                        $captchaError = 'کپچا تأیید نشد. لطفا دوباره تلاش کنید یا صفحه را بروز کنید.';
                    }
                }
            } elseif ($cfg['type'] === CaptchaService::TYPE_PERSIAN_NUMBER) {
                $captchaValid = CaptchaService::validatePersianNumber($request->input('captcha_answer', ''));
                if (!$captchaValid) {
                    $captchaError = 'کپچا اشتباه است یا منقضی شده. لطفا صفحه را بروز کنید و دوباره عدد نمایش‌داده‌شده را وارد کنید.';
                }
            } else {
                $captchaValid = CaptchaService::validateNormal($request->input('captcha_answer', ''));
                if (!$captchaValid) {
                    $captchaError = 'کپچا اشتباه است یا منقضی شده. لطفا صفحه را بروز کنید و دوباره جواب را وارد کنید.';
                }
            }
            if (!$captchaValid) {
                return back()->withInput()->withErrors(['captcha' => $captchaError]);
            }
        }

        $credentials = $request->validate([
            'mobile'   => ['required', new IranianMobile],
            'password' => ['required'],
        ]);

        // تلاش برای ورود با موبایل
        if (Auth::attempt([
            'mobile' => $credentials['mobile'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            $user = auth()->user();
            // اکانت غیرفعال (توسط ادمین)
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active') && !($user->is_active ?? true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors(['mobile' => 'این اکانت غیرفعال شده است. با پشتیبانی تماس بگیرید.'])->onlyInput('mobile');
            }

            $request->session()->regenerate();

            ActivityLogger::login();

            // اگر ادمین باشد
            if ($user->is_admin ?? false) {
                return redirect()->route('admin.dashboard');
            }

            // کاربر عادی
            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'mobile' => 'موبایل یا رمز عبور اشتباه است.',
        ])->onlyInput('mobile');
    }

    /**
     * خروج
     */
    public function logout(Request $request)
    {
        ActivityLogger::logout();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show');
    }
}
