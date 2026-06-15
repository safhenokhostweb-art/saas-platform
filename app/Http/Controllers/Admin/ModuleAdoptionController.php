<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class ModuleAdoptionController extends Controller
{
    public function index()
    {
        // ماژول‌های کلیدی که می‌خواهیم پیگیری کنیم
        $trackedSlugs = ['crm', 'inventory', 'courses', 'subscription', 'forms', 'tickets'];

        $modules = Module::whereIn('slug', $trackedSlugs)->get()->keyBy('slug');
        $totalSites = Site::where('is_active', true)->count();

        // برای هر ماژول: چند سایت فعال دارند
        $adoption = [];
        foreach ($trackedSlugs as $slug) {
            $module = $modules->get($slug);
            if (!$module) {
                continue;
            }
            $count = DB::table('site_modules')
                ->where('module_id', $module->id)
                ->where('is_active', true)
                ->count();

            $adoption[$slug] = [
                'module' => $module,
                'active_count' => $count,
                'percentage' => $totalSites > 0 ? round($count / $totalSites * 100) : 0,
            ];
        }

        // سایت‌هایی که CRM دارند (برای لیست تفصیلی)
        $crmModule = $modules->get('crm');
        $inventoryModule = $modules->get('inventory');

        $crmSites = collect();
        $inventorySites = collect();

        if ($crmModule) {
            $crmSiteIds = DB::table('site_modules')
                ->where('module_id', $crmModule->id)
                ->where('is_active', true)
                ->pluck('site_id');
            $crmSites = Site::whereIn('id', $crmSiteIds)
                ->where('is_active', true)
                ->with('customer')
                ->orderBy('title')
                ->get();
        }

        if ($inventoryModule) {
            $inventorySiteIds = DB::table('site_modules')
                ->where('module_id', $inventoryModule->id)
                ->where('is_active', true)
                ->pluck('site_id');
            $inventorySites = Site::whereIn('id', $inventorySiteIds)
                ->where('is_active', true)
                ->with('customer')
                ->orderBy('title')
                ->get();
        }

        // تازه‌ترین فعال‌سازی‌های ماژول در ۳۰ روز گذشته
        $recentActivations = DB::table('site_modules')
            ->join('sites', 'sites.id', '=', 'site_modules.site_id')
            ->join('modules', 'modules.id', '=', 'site_modules.module_id')
            ->where('site_modules.is_active', true)
            ->whereIn('modules.slug', $trackedSlugs)
            ->where('site_modules.activated_at', '>=', now()->subDays(30))
            ->orderByDesc('site_modules.activated_at')
            ->select('sites.title as site_title', 'sites.domain', 'modules.title as module_title', 'modules.slug', 'site_modules.activated_at')
            ->limit(10)
            ->get();

        return view('admin.module-adoption.index', compact(
            'adoption',
            'totalSites',
            'crmSites',
            'inventorySites',
            'recentActivations',
            'trackedSlugs'
        ));
    }
}
