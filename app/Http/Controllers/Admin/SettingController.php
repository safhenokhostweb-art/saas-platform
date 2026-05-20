<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * نمایش صفحه تنظیمات
     */
    public function index()
    {
        $forceComplete = (string) Setting::get('force_complete_profile', '1') === '1';
        $formEnabled = (string) Setting::get('profile_form_enabled', '1') === '1';

        return view('admin.settings.index', compact('forceComplete', 'formEnabled'));
    }

    /**
     * تغییر وضعیت نمایش فرم پروفایل (فعال/غیرفعال)
     */
    public function toggleProfileForm()
    {
        $setting = Setting::firstOrCreate(
            ['key' => 'profile_form_enabled'],
            ['value' => '1']
        );

        $setting->value = ($setting->value === '1' || $setting->value == 1) ? '0' : '1';
        $setting->save();

        $status = $setting->value === '1' ? 'فعال' : 'غیرفعال';
        return back()->with('success', "نمایش فرم پروفایل اکنون {$status} است");
    }

    /**
     * تغییر وضعیت اجبار تکمیل پروفایل قبل از ورود
     */
    public function toggleForceCompleteProfile()
    {
        $setting = Setting::firstOrCreate(
            ['key' => 'force_complete_profile'],
            ['value' => '1']
        );

        $setting->value = ($setting->value === '1' || $setting->value == 1) ? '0' : '1';
        $setting->save();

        $status = $setting->value === '1' ? 'فعال' : 'غیرفعال';
        return back()->with('success', "اجبار تکمیل پروفایل اکنون {$status} است");
    }
}
