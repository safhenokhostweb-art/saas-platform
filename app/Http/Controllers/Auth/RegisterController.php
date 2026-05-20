<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Helpers\PlatformSettings;
use App\Models\User;
use App\Rules\IranianMobile;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{

public function showRegisterForm()
{
    if (!PlatformSettings::isRegistrationOpen()) {
        return redirect()->route('login.show')->with('error', 'در حال حاضر امکان ثبت‌نام وجود ندارد.');
    }
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
    return view('auth.register', compact('captcha'));
}

public function register(Request $request)
{
    if (!PlatformSettings::isRegistrationOpen()) {
        return redirect()->route('login.show')->with('error', 'در حال حاضر امکان ثبت‌نام وجود ندارد.');
    }
    $cfg = CaptchaService::getAuthCaptchaConfig();
    if ($cfg['enabled']) {
        $captchaValid = false;
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
    $request->validate([
        'mobile'   => ['required', 'unique:users,mobile', new IranianMobile],
        'password' => 'required|min:6|confirmed',
    ]);
// dd($request->all());

    User::create([
        'mobile'   => $request->mobile,
        'password' => Hash::make($request->password),
    ]);

    return redirect()->route('login.show')->with('success', 'ثبت‌نام با موفقیت انجام شد');
}

}
