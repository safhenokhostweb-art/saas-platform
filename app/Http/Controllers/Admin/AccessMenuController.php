<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Setting;
use App\Helpers\AdminMenu;
use App\Helpers\CustomerMenu;
use App\Helpers\PlatformModules;
use Illuminate\Http\Request;

class AccessMenuController extends Controller
{
    public function index()
    {
        $menuVisibility = AdminMenu::all();
        $customerMenuVisibility = CustomerMenu::all();
        $modules = Module::orderBy('title')->get();
        $platformModules = PlatformModules::all();

        return view('admin.access.index', compact('menuVisibility', 'customerMenuVisibility', 'modules', 'platformModules'));
    }

    public function update(Request $request)
    {
        $section = $request->input('section');

        // ذخیره ماژول‌های مختص پلتفرم
        if ($section === 'platform_modules' || $request->has('save_platform_modules')) {
            foreach (array_keys(PlatformModules::MODULES) as $key) {
                $value = $request->has('platform_module_' . $key) ? '1' : '0';
                Setting::updateOrCreate(
                    ['key' => 'platform_module_' . $key],
                    ['value' => $value]
                );
            }
            return back()->with('success', 'ماژول‌های مختص پلتفرم به‌روزرسانی شد.');
        }

        // فقط وقتی دکمه «ذخیره منوی ادمین» زده شده: ذخیره نمایش منوی کناری پنل ادمین
        if ($section === 'admin_menu' || $request->has('save_admin_menu')) {
            foreach (AdminMenu::MENU_KEYS as $key => $label) {
                $value = $request->has('menu_' . $key) ? '1' : '0';
                Setting::updateOrCreate(
                    ['key' => 'admin_menu_' . $key],
                    ['value' => $value]
                );
            }
            return back()->with('success', 'منوی کناری پنل ادمین به‌روزرسانی شد.');
        }

        // فقط وقتی دکمه «ذخیره ماژول‌های مشتری» زده شده: ذخیره وضعیت ماژول‌ها برای سایت مشتری (بدون تغییر منوی ادمین)
        if ($section === 'modules' || $request->has('save_modules')) {
            $moduleIds = $request->input('modules', []);
            Module::query()->update(['is_active' => false]);
            if (!empty($moduleIds)) {
                Module::whereIn('id', $moduleIds)->update(['is_active' => true]);
            }
            return back()->with('success', 'ماژول‌های قابل اختصاص به سایت مشتری به‌روزرسانی شد.');
        }

        // فقط وقتی دکمه «ذخیره منوی پنل مشتری» زده شده: ذخیره نمایش منوی پنل برای مشتری (کاربر غیرادمین)
        if ($section === 'customer_menu' || $request->has('save_customer_menu')) {
            foreach (CustomerMenu::MENU_KEYS as $key => $label) {
                $value = $request->has('customer_menu_' . $key) ? '1' : '0';
                Setting::updateOrCreate(
                    ['key' => 'customer_menu_' . $key],
                    ['value' => $value]
                );
            }
            return back()->with('success', 'منوی پنل مشتری به‌روزرسانی شد.');
        }

        return back()->with('info', 'هیچ تغییری ذخیره نشد.');
    }
}
