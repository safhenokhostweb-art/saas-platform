<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت ماژول‌ها (کاتالوگ سراسری، قیمت، مارکت‌پلیس، آمار).
 */
class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::orderBy('slug')->get();
        $sitesCount = Site::count();
        $moduleSiteCounts = DB::table('site_modules')
            ->where('is_active', true)
            ->selectRaw('module_id, count(*) as cnt')
            ->groupBy('module_id')
            ->pluck('cnt', 'module_id');

        $licensedCounts = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('module_licenses')) {
            $licensedCounts = DB::table('module_licenses')
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereNull('revoked_at')
                ->selectRaw('module_id, count(*) as cnt')
                ->groupBy('module_id')
                ->pluck('cnt', 'module_id')
                ->all();
        }

        $productByModuleId = Product::where('type', Product::TYPE_MODULE_ADDON)
            ->whereNotNull('module_id')
            ->get()
            ->keyBy('module_id');

        return view('admin.modules.index', compact('modules', 'sitesCount', 'moduleSiteCounts', 'licensedCounts', 'productByModuleId'));
    }

    public function edit(Module $module)
    {
        $productInStore = \App\Models\Product::where('type', \App\Models\Product::TYPE_MODULE_ADDON)
            ->where('module_id', $module->id)
            ->first();
        return view('admin.modules.edit', compact('module', 'productInStore'));
    }

    public function update(Request $request, Module $module)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price_irr' => 'nullable|integer|min:0',
            'price_promo_irr' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:8',
            'license_type_default' => 'required|in:one_time,subscription',
            'marketplace_visible' => 'boolean',
            'free_assignable' => 'boolean',
            'license_required' => 'boolean',
            'category' => 'nullable|string|max:64',
            'screenshots' => 'nullable|string', // JSON or comma URLs
        ]);

        $request->validate([
            'module_type' => 'required|in:internal,license,paid',
        ]);

        $oldType = $module->type;

        $data = $request->only([
            'title', 'description', 'short_description',
            'price_irr', 'price_promo_irr', 'currency', 'license_type_default',
            'marketplace_visible', 'free_assignable', 'license_required', 'category',
        ]);
        $data['marketplace_visible'] = $request->boolean('marketplace_visible', true);
        $data['free_assignable'] = $request->boolean('free_assignable', false);
        $data['license_required'] = $request->boolean('license_required', false);
        $data['price_irr'] = $request->filled('price_irr') ? (int) $request->price_irr : null;
        $data['price_promo_irr'] = $request->filled('price_promo_irr') ? (int) $request->price_promo_irr : null;

        if ($request->filled('screenshots')) {
            $urls = array_filter(array_map('trim', explode("\n", $request->screenshots)));
            $data['screenshots'] = $urls;
        } else {
            $data['screenshots'] = null;
        }

        // نگاشت نوع نمایشی ماژول (داخلی / مبتنی بر لایسنس / پولی) به مقدار ذخیره‌شده در دیتابیس.
        $uiType = $request->input('module_type');
        $newType = match ($uiType) {
            'internal' => Module::TYPE_INTERNAL,
            'paid' => Module::TYPE_PAID,
            'license' => Module::TYPE_CORE, // ماژول‌های «لایسنس‌محور» در دیتابیس به‌صورت core ذخیره می‌شوند.
            default => $module->type,
        };
        $data['type'] = $newType;

        DB::transaction(function () use ($module, $data, $oldType, $newType) {
            $module->update($data);

            // منطق امن تغییر نوع:
            // - internal → سایر حالات: هیچ سایتی خودکار ماژول را دریافت نمی‌کند (site_modules دست‌نخورده می‌ماند).
            // - هر حالت → internal: دسترسی همهٔ سایت‌ها به‌صورت نرم غیرفعال می‌شود (بدون حذف داده).
            if ($newType === Module::TYPE_INTERNAL && $oldType !== Module::TYPE_INTERNAL) {
                if (\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                    \Illuminate\Support\Facades\DB::table('site_modules')
                        ->where('module_id', $module->id)
                        ->update(['is_active' => false]);
                }
            }

            // جابه‌جایی بین core (license) و paid:
            // - هیچ تغییری روی site_modules انجام نمی‌دهیم تا سایت‌هایی که هم‌اکنون دسترسی دارند، آن را از دست ندهند.
            // - قوانین جدید توزیع از این پس از روی تنظیمات قیمت/لایسنس اعمال می‌شود.
        });

        return redirect()->route('admin.modules.index')->with('success', 'ماژول به‌روزرسانی شد.');
    }

    /**
     * بروزرسانی گروهی ماژول‌ها (نوع ماژول و فلگ‌های توزیع).
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'integer|exists:modules,id',
            'bulk_action' => 'required|string|in:' . implode(',', [
                'set_type_internal',
                'set_type_license',
                'set_type_paid',
                'marketplace_on',
                'marketplace_off',
                'free_assignable_on',
                'free_assignable_off',
                'license_required_on',
                'license_required_off',
            ]),
        ]);

        $ids = $data['module_ids'];
        $action = $data['bulk_action'];

        $modules = Module::whereIn('id', $ids)->get();

        foreach ($modules as $module) {
            $oldType = $module->type;
            $updates = [];

            switch ($action) {
                case 'set_type_internal':
                    $newType = Module::TYPE_INTERNAL;
                    $updates['type'] = $newType;
                    break;
                case 'set_type_license':
                    $newType = Module::TYPE_CORE;
                    $updates['type'] = $newType;
                    break;
                case 'set_type_paid':
                    $newType = Module::TYPE_PAID;
                    $updates['type'] = $newType;
                    break;
                case 'marketplace_on':
                    $newType = $oldType;
                    $updates['marketplace_visible'] = true;
                    break;
                case 'marketplace_off':
                    $newType = $oldType;
                    $updates['marketplace_visible'] = false;
                    break;
                case 'free_assignable_on':
                    $newType = $oldType;
                    $updates['free_assignable'] = true;
                    break;
                case 'free_assignable_off':
                    $newType = $oldType;
                    $updates['free_assignable'] = false;
                    break;
                case 'license_required_on':
                    $newType = $oldType;
                    $updates['license_required'] = true;
                    break;
                case 'license_required_off':
                    $newType = $oldType;
                    $updates['license_required'] = false;
                    break;
                default:
                    $newType = $oldType;
                    break;
            }

            if (empty($updates)) {
                continue;
            }

            DB::transaction(function () use ($module, $updates, $oldType, $newType) {
                $module->update($updates);

                // همان منطق ایمن تغییر نوع برای حالت گروهی هم اعمال می‌شود.
                if (($updates['type'] ?? null) === Module::TYPE_INTERNAL && $oldType !== Module::TYPE_INTERNAL) {
                    if (\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                        \Illuminate\Support\Facades\DB::table('site_modules')
                            ->where('module_id', $module->id)
                            ->update(['is_active' => false]);
                    }
                }
            });
        }

        return redirect()->route('admin.modules.index')->with('success', 'تغییرات گروهی ماژول‌ها اعمال شد.');
    }
}
