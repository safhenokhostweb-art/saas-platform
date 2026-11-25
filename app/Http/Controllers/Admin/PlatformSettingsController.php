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
        $gapGptStatus = PlatformSettings::gapGptStatus();
        return view('admin.platform-settings.index', compact('general', 'theme', 'font', 'upload', 'autoExpiry', 'smsExpiry', 'guideWidget', 'email', 'sms', 'apiKeys', 'featureFlags', 'gapGptStatus'));
    }

    public function update(Request $request)
    {
        $themeRules = [];
        foreach (array_keys(PlatformSettings::THEME_KEYS) as $k) {
            $themeRules[$k] = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }
        $request->validate(array_merge($themeRules, [
            'platform_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:64',
            'contact_address' => 'nullable|string|max:500',
            'gapgpt_api_key' => 'nullable|string|max:500',
            'gapgpt_base_url' => 'nullable|url|max:255',
            'gapgpt_model' => 'nullable|string|max:120',
        ]));

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

        if ($request->hasFile('platform_logo') && $request->file('platform_logo')->isValid()) {
            $mimes = implode(',', PlatformSettings::LOGO_ALLOWED_EXTENSIONS);
            $request->validate([
                'platform_logo' => "image|max:2048|mimes:{$mimes}",
            ]);
            try {
                PlatformSettings::storeLogo($request->file('platform_logo'));
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['platform_logo' => $e->getMessage()]);
            }
            unset($data['platform_logo']);
        } elseif ($request->filled('platform_logo_path')) {
            $raw = trim($request->platform_logo_path);
            // Only accept an absolute HTTP/HTTPS URL — never accept local absolute paths
            // or anything that could be path traversal (e.g. "/etc/passwd").
            if (preg_match('#^https?://#i', $raw)) {
                $data['platform_logo'] = $raw;
            } else {
                // Treat as a relative storage path — normalize and reject traversal.
                $path = PlatformSettings::normalizeStoragePath($raw);
                // Reject any path that still contains traversal sequences after normalization,
                // or that normalizeStoragePath() left absolute (Unix or Windows drive) because
                // it had no recognizable storage marker to safely extract from.
                if ($path !== '' && !str_contains($path, '..') && !str_starts_with($path, '/') && !preg_match('#^[A-Za-z]:/#', $path)) {
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

        try {
            PlatformSettings::saveGapGptSettings(
                $request->input('gapgpt_api_key'),
                $request->boolean('gapgpt_clear_key'),
                $request->input('gapgpt_base_url'),
                $request->input('gapgpt_model')
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed saving GapGPT settings: ' . $e->getMessage());
            return back()->withInput()->with('error', 'خطا در ذخیره تنظیمات GapGPT: ' . $e->getMessage());
        }

        return back()->with('success', 'تنظیمات عمومی پلتفرم ذخیره شد.');
    }
}
