<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ModuleLabels;
use App\Helpers\AdminMenu;
use App\Helpers\CustomerMenu;
use App\Models\ModuleLabel;
use Illuminate\Http\Request;

class ModuleLabelController extends Controller
{
    /**
     * نمایش فرم ویرایش عنوان و جزئیات ماژول‌ها.
     * ادمین اصلی: siteId = null (پلتفرم)
     * مشتری: در صورت پشتیبانی بعدی می‌توان site را از route یا session گرفت.
     */
    public function index(Request $request)
    {
        $siteId = $request->get('site_id'); // اختیاری؛ برای مشتری
        $isAdmin = app(\App\Services\AuthorizationService::class)->canAccessAdmin(auth()->user());

        $keys = $isAdmin ? ModuleLabels::keysForAdmin() : ModuleLabels::keysForCustomer();
        $labelsByKey = ModuleLabel::forSite($siteId)
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $items = [];
        $menuKeys = $isAdmin ? AdminMenu::MENU_KEYS : CustomerMenu::MENU_KEYS;
        foreach ($keys as $key) {
            $label = $labelsByKey->get($key);
            $items[] = [
                'key' => $key,
                'default_title' => ModuleLabels::defaultTitle($key),
                'default_description' => ModuleLabels::defaultDescription($key),
                'custom_title' => $label ? $label->custom_title : '',
                'custom_description' => $label ? $label->custom_description : '',
            ];
        }

        $pageTitle = $isAdmin
            ? 'عنوان و جزئیات ماژول‌ها (پنل ادمین)'
            : 'عنوان و جزئیات ماژول‌ها';

        return view('admin.module-labels.index', compact('items', 'siteId', 'pageTitle'));
    }

    public function update(Request $request)
    {
        $siteId = $request->get('site_id');
        $isAdmin = app(\App\Services\AuthorizationService::class)->canAccessAdmin(auth()->user());
        $keys = $isAdmin ? ModuleLabels::keysForAdmin() : ModuleLabels::keysForCustomer();

        $request->validate([
            'labels' => 'array',
            'labels.*.key' => 'required|string',
            'labels.*.custom_title' => 'nullable|string|max:255',
            'labels.*.custom_description' => 'nullable|string|max:5000',
        ]);

        $labels = $request->input('labels', []);
        foreach ($labels as $item) {
            $key = $item['key'] ?? null;
            if (!$key || !in_array($key, $keys, true)) {
                continue;
            }
            $customTitle = isset($item['custom_title']) ? trim($item['custom_title']) : null;
            $customDescription = isset($item['custom_description']) ? trim($item['custom_description']) : null;

            $label = ModuleLabel::where('site_id', $siteId)->where('key', $key)->first();
            if ($label) {
                $label->update([
                    'custom_title' => $customTitle ?: null,
                    'custom_description' => $customDescription ?: null,
                ]);
            } else {
                ModuleLabel::create([
                    'site_id' => $siteId,
                    'key' => $key,
                    'custom_title' => $customTitle ?: null,
                    'custom_description' => $customDescription ?: null,
                ]);
            }
        }

        return back()->with('success', 'عنوان و جزئیات ماژول‌ها ذخیره شد.');
    }
}
