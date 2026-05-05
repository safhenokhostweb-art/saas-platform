<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\PlatformSettings;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    public function index()
    {
        $general = PlatformSettings::allGeneral();
        $theme = PlatformSettings::allTheme();
        $font = PlatformSettings::allFont();
        $upload = PlatformSettings::allUpload();
        $autoExpiry = PlatformSettings::allAutoExpiry();
        $smsExpiry = PlatformSettings::allSmsExpiry();
        $guideWidget = PlatformSettings::allGuideWidget();
        $email = PlatformSettings::allEmail();
        $sms = PlatformSettings::allSms();
        $apiKeys = PlatformSettings::allApiKeys();
        $featureFlags = PlatformSettings::allFeatureFlags();
        return view('admin.platform-settings.index', compact('general', 'theme', 'font', 'upload', 'autoExpiry', 'smsExpiry', 'guideWidget', 'email', 'sms', 'apiKeys', 'featureFlags'));
    }

    public function update(Request $request)
    {
        $data = $request->only(array_merge(
            array_keys(PlatformSettings::GENERAL_KEYS),
            array_keys(PlatformSettings::THEME_KEYS),
            array_keys(PlatformSettings::FONT_KEYS),
            array_keys(PlatformSettings::UPLOAD_KEYS),
            array_keys(PlatformSettings::AUTO_EXPIRY_KEYS),
            array_keys(PlatformSettings::SMS_EXPIRY_KEYS),
            array_keys(PlatformSettings::GUIDE_WIDGET_KEYS),
            array_keys(PlatformSettings::EMAIL_KEYS),
            array_keys(PlatformSettings::SMS_KEYS),
            array_keys(PlatformSettings::API_KEYS_KEYS),
            array_keys(PlatformSettings::FEATURE_FLAGS_KEYS)
        ));

        if ($request->hasFile('platform_logo')) {
            $request->validate(['platform_logo' => 'image|max:2048']);
            PlatformSettings::storeLogo($request->file('platform_logo'));
            unset($data['platform_logo']);
        } elseif ($request->filled('platform_logo_path')) {
            $raw = trim($request->platform_logo_path);
            if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) {
                $data['platform_logo'] = $raw;
            } else {
                $path = PlatformSettings::normalizeStoragePath($raw);
                if ($path !== '') {
                    $data['platform_logo'] = $path;
                }
            }
        }
        if (!array_key_exists('platform_logo', $data)) {
            unset($data['platform_logo']);
        }

        if (!isset($data['registration_open'])) {
            $data['registration_open'] = '0';
        } else {
            $data['registration_open'] = $request->boolean('registration_open') ? '1' : '0';
        }
        if (!isset($data['guide_widget_enabled'])) {
            $data['guide_widget_enabled'] = '0';
        } else {
            $data['guide_widget_enabled'] = $request->boolean('guide_widget_enabled') ? '1' : '0';
        }
        foreach (array_keys(PlatformSettings::AUTO_EXPIRY_KEYS) as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }
        foreach (array_keys(PlatformSettings::SMS_EXPIRY_KEYS) as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }
        foreach (array_keys(PlatformSettings::FEATURE_FLAGS_KEYS) as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }

        // رمزها و کلیدهای حساس: اگر خالی باشند، مقدار قبلی را نگه دار
        foreach (['email_password', 'sms_api_key', 'api_recaptcha_secret_key'] as $sensitive) {
            if (isset($data[$sensitive]) && (string) $data[$sensitive] === '') {
                unset($data[$sensitive]);
            }
        }

        PlatformSettings::save($data);

        return back()->with('success', 'تنظیمات عمومی پلتفرم ذخیره شد.');
    }
}
