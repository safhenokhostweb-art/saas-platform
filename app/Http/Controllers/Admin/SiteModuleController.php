<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleAssignmentService;
use App\Services\SiteModuleEntitlementAuditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 17-D — this controller no longer touches site_modules directly at
 * all (no sync()/attach()/updateExistingPivot()). Every activation goes
 * through ModuleAssignmentService::enableForSite() (which owns the license/
 * internal/retired/package/conflict guards and the real CustomerInstallation
 * Pipeline dispatch via ModuleDeploymentRequestService); every deactivation
 * goes through ModuleAssignmentService::disableForSite(). This controller's
 * only job is: validate input, call the service, translate the result into
 * a message the admin understands.
 */
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

        $assignableModules = $this->assignmentService->modulesForManualAssign();
        $assignableIds = $assignableModules->pluck('id')->all();

        $site->load('modules');
        $currentActiveMap = [];
        foreach ($assignableModules as $m) {
            $pivot = $site->modules->firstWhere('id', $m->id);
            $currentActiveMap[$m->id] = $pivot ? (bool) $pivot->pivot->is_active : false;
        }

        // Phase 17-D — the view's checkboxes have no hidden "0" fallback
        // input, so an unchecked box is simply ABSENT from $modulesInput
        // (standard HTML behavior), not present with a falsy value. A
        // module missing from the input entirely must be treated as
        // "no change requested" (preserve its current state) exactly like
        // the pre-17-D sync()-based code did — never silently disabled.
        // Only a module explicitly present with a falsy value (not
        // currently produced by this view, but honored if a future form
        // adds one) is treated as "wants inactive."
        $requestedActiveIds = [];
        $isList = !empty($modulesInput) && array_keys($modulesInput) === range(0, count($modulesInput) - 1);
        if ($isList) {
            $requestedActiveIds = array_map('intval', $modulesInput);
        } else {
            foreach ($assignableIds as $id) {
                if (array_key_exists($id, $modulesInput) || array_key_exists((string) $id, $modulesInput)) {
                    $value = $modulesInput[$id] ?? $modulesInput[(string) $id];
                    if ((bool) $value) {
                        $requestedActiveIds[] = $id;
                    }
                } elseif ($currentActiveMap[$id]) {
                    $requestedActiveIds[] = $id;
                }
            }
        }

        $results = $this->assignmentService->syncManualAssignmentsForSite(
            $site,
            $assignableIds,
            $requestedActiveIds,
            Auth::id()
        );

        // سینک فیچرها: جدول site_module_features با site_module_id کار می‌کند، نه site_id
        // (بدون تغییر منطق — مستقل از فعال/غیرفعال شدن ماژول در بالا)
        $site->load('modules');
        foreach ($site->modules as $module) {
            $siteModule = DB::table('site_modules')
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->first();
            if (!$siteModule) {
                continue;
            }
            $siteModuleId = $siteModule->id;
            $featureIdsForModule = \App\Models\ModuleFeature::where('module_id', $module->id)->pluck('id')->all();
            DB::table('site_module_features')
                ->where('site_module_id', $siteModuleId)
                ->delete();
            foreach ($featureIdsForModule as $fid) {
                if (!empty($featuresInput[$fid])) {
                    DB::table('site_module_features')->insert([
                        'site_module_id' => $siteModuleId,
                        'module_feature_id' => $fid,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return back()->with(...$this->summarizeBulkResult($results));
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
            $message = 'این ماژول از طریق این بخش قابل تغییر نیست (فقط ماژول‌های رایگان یا لایسنس‌دار).';
            return $this->respond($request, false, $message, $site);
        }

        $current = $site->modules()->where('module_id', $moduleId)->first();
        $wasActive = $current && $current->pivot->is_active;

        if ($wasActive) {
            $result = $this->assignmentService->disableForSite($site, $moduleId);
            $message = $result['success']
                ? 'ماژول غیرفعال شد.'
                : ($result['error'] ?? 'غیرفعال‌سازی ماژول با خطا مواجه شد.');
        } else {
            $result = $this->assignmentService->enableForSite($site, $moduleId, Auth::id());
            // Phase 17-D — Part 6: success here means the Pipeline was
            // queued, not that the module is already installed on the site.
            $message = $result['success']
                ? 'نصب ماژول در صف قرار گرفت و به‌زودی روی سایت اعمال می‌شود.'
                : ($result['error'] ?? 'فعال‌سازی ماژول با خطا مواجه شد.');
        }

        return $this->respond($request, $result['success'], $message, $site);
    }

    private function respond(Request $request, bool $ok, string $message, Site $site)
    {
        if ($request->wantsJson()) {
            $site->load('modules');
            $activeSlugs = $site->modules->filter(fn ($m) => $m->pivot->is_active)->pluck('slug')->all();
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'error' => $ok ? null : $message,
                'modules' => $activeSlugs,
            ], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * Phase 17-D — Part 4/6: تبدیل نتیجهٔ per-module syncManualAssignmentsForSite()
     * به یک پیام خلاصهٔ واحد — چون View فعلی فقط یک flash message ساده
     * نمایش می‌دهد (کم‌ریسک‌ترین سازگاری با UI موجود، به‌جای بازطراحی کامل صفحه).
     *
     * @param array<int, array{module_id:int, slug:string, title:string, result:string, error?:string}> $results
     * @return array{0: string, 1: string} [flashKey, message]
     */
    private function summarizeBulkResult(array $results): array
    {
        $enabled = array_filter($results, fn ($r) => $r['result'] === 'enabled');
        $disabled = array_filter($results, fn ($r) => $r['result'] === 'disabled');
        $failed = array_filter($results, fn ($r) => $r['result'] === 'failed');

        $parts = [];
        if (count($enabled)) {
            $parts[] = count($enabled) . ' ماژول در صف نصب قرار گرفت';
        }
        if (count($disabled)) {
            $parts[] = count($disabled) . ' ماژول غیرفعال شد';
        }
        if (count($failed)) {
            $reasons = implode('؛ ', array_map(
                fn ($r) => $r['title'] . ': ' . ($r['error'] ?? 'نامشخص'),
                $failed
            ));
            $parts[] = count($failed) . " ماژول ناموفق ({$reasons})";
        }

        if (empty($parts)) {
            return ['success', 'هیچ تغییری در ماژول‌ها اعمال نشد.'];
        }

        return [count($failed) ? 'error' : 'success', implode('، ', $parts) . '.'];
    }
}
