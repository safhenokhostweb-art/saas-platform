<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleAssignmentService;
use App\Services\SiteModuleEntitlementAuditor;
use Illuminate\Http\Request;

class SiteModuleController extends Controller
{
    public function __construct(
        protected ModuleAssignmentService $assignmentService,
        protected SiteModuleEntitlementAuditor $entitlementAuditor,
    ) {}

    public function edit(Site $site)
    {
        // فقط ماژول‌های قابل اختصاص دستی: FREE ASSIGNABLE و LICENSE-ONLY (PAID در فروشگاه tenant)
        $modules = $this->assignmentService->modulesForManualAssign()->load('features');
        $site->load('modules');
        $enabledFeatureIds = $site->getEnabledModuleFeatureIds();

        $licenseService = app(\App\Services\ModuleLicenseService::class);
        $hasLicenseByModuleId = [];
        foreach ($modules as $m) {
            $hasLicenseByModuleId[$m->id] = $m->isLicenseOnly()
                ? $licenseService->hasValidLicense($site, $m->id)
                : true;
        }

        return view('admin.sites.modules', compact('site', 'modules', 'enabledFeatureIds', 'hasLicenseByModuleId'));
    }

    public function update(Request $request, Site $site)
    {
        $modulesInput = $request->input('modules', []);
        $featuresInput = $request->input('features', []);

        $assignableModules = $this->assignmentService->modulesForManualAssign()->keyBy('id');
        $licenseService = app(\App\Services\ModuleLicenseService::class);
        $site->load('modules');

        $beforeActive = [];
        foreach ($site->modules as $m) {
            $beforeActive[(int) $m->id] = (bool) $m->pivot->is_active;
        }

        // همهٔ ماژول‌های فعلی سایت را نگه می‌داریم (شامل PAID که از خرید فعال شده)
        $syncModules = [];
        foreach ($site->modules as $m) {
            $syncModules[$m->id] = [
                'is_active' => (bool) $m->pivot->is_active,
                'activated_at' => $m->pivot->activated_at,
            ];
        }

        foreach ($modulesInput as $moduleId => $isActive) {
            $moduleId = (int) $moduleId;
            $module = $assignableModules->get($moduleId);
            if (! $module) {
                continue;
            }
            $active = (bool) $isActive;
            if ($active && $module->isLicenseOnly() && ! $licenseService->hasValidLicense($site, $moduleId)) {
                continue;
            }
            $current = $site->modules->firstWhere('id', $moduleId);
            $wasActive = $current && $current->pivot->is_active;
            $activatedAt = $active ? ($wasActive ? ($current->pivot->activated_at ?? now()) : now()) : null;
            $syncModules[$moduleId] = [
                'is_active' => $active,
                'activated_at' => $activatedAt,
            ];
        }
        $site->modules()->sync($syncModules);

        $site->load('modules');
        foreach ($assignableModules as $moduleId => $module) {
            $mid = (int) $moduleId;
            $before = $beforeActive[$mid] ?? false;
            $pivotRow = $site->modules->firstWhere('id', $mid);
            $after = $pivotRow ? (bool) $pivotRow->pivot->is_active : false;
            $this->entitlementAuditor->syncAfterPivotChange($site, $module, $before, $after);
        }

        // سینک فیچرها: جدول site_module_features با site_module_id کار می‌کند، نه site_id
        $site->load('modules');
        foreach ($site->modules as $module) {
            $siteModule = \Illuminate\Support\Facades\DB::table('site_modules')
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->first();
            if (!$siteModule) {
                continue;
            }
            $siteModuleId = $siteModule->id;
            $featureIdsForModule = \App\Models\ModuleFeature::where('module_id', $module->id)->pluck('id')->all();
            \Illuminate\Support\Facades\DB::table('site_module_features')
                ->where('site_module_id', $siteModuleId)
                ->delete();
            foreach ($featureIdsForModule as $fid) {
                if (!empty($featuresInput[$fid])) {
                    \Illuminate\Support\Facades\DB::table('site_module_features')->insert([
                        'site_module_id' => $siteModuleId,
                        'module_feature_id' => $fid,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return back()->with('success', 'ماژول‌ها با موفقیت ذخیره شدند.');
    }

    /**
     * سوئیچ سریع فعال/غیرفعال یک ماژول برای سایت (فقط برای ماژول‌های قابل اختصاص دستی).
     */
    public function toggle(Request $request, Site $site)
    {
        $request->validate(['module_id' => 'required|exists:modules,id']);
        $moduleId = (int) $request->module_id;
        $module = $this->assignmentService->modulesForManualAssign()->firstWhere('id', $moduleId);
        if (! $module) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'ماژول قابل اختصاص دستی نیست یا یافت نشد.'], 404);
            }
            return back()->with('error', 'این ماژول از طریق این بخش قابل تغییر نیست (فقط ماژول‌های رایگان یا لایسنس‌دار).');
        }
        if ($module->isLicenseOnly() && ! app(\App\Services\ModuleLicenseService::class)->hasValidLicense($site, $moduleId)) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'برای فعال‌سازی ابتدا لایسنس ماژول را صادر کنید.'], 403);
            }
            return back()->with('error', 'برای فعال‌سازی این ماژول ابتدا لایسنس را از بخش لایسنس ماژول صادر کنید.');
        }

        $current = $site->modules()->where('module_id', $moduleId)->first();
        $wasActive = $current && $current->pivot->is_active;
        $newActive = ! $wasActive;

        $assignableIds = $this->assignmentService->modulesForManualAssign()->pluck('id')->all();
        $site->load('modules');
        $sync = [];
        foreach ($site->modules as $m) {
            $sync[$m->id] = [
                'is_active' => $m->id === $moduleId ? $newActive : (bool) $m->pivot->is_active,
                'activated_at' => $m->id === $moduleId && $newActive ? now() : $m->pivot->activated_at,
            ];
        }
        if (! isset($sync[$moduleId])) {
            $sync[$moduleId] = ['is_active' => $newActive, 'activated_at' => $newActive ? now() : null];
        }
        $site->modules()->sync($sync);

        $site->load('modules');
        $pivotRow = $site->modules->firstWhere('id', $moduleId);
        $nowActive = $pivotRow ? (bool) $pivotRow->pivot->is_active : false;
        $this->entitlementAuditor->syncAfterPivotChange($site, $module, (bool) $wasActive, $nowActive);

        if ($request->wantsJson()) {
            $site->load('modules');
            $activeSlugs = $site->modules->filter(fn ($m) => $m->pivot->is_active)->pluck('slug')->all();
            return response()->json(['ok' => true, 'modules' => $activeSlugs]);
        }
        return back()->with('success', 'ماژول به‌روزرسانی شد.');
    }
}
