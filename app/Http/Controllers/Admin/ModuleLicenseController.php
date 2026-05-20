<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\ModulePurchase;
use App\Models\Site;
use App\Services\EntitlementLedger;
use App\Services\ModuleLicenseService;
use App\Services\ModulePurchaseService;
use Illuminate\Http\Request;

/**
 * مدیریت لایسنس‌های ماژول: لیست، اعطا، لغو.
 */
class ModuleLicenseController extends Controller
{
    public function index(Request $request)
    {
        $query = ModuleLicense::with(['site.customer', 'module', 'grantedBy'])
            ->orderByDesc('created_at');

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }
        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('status', 'active')
                    ->whereNull('revoked_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            } else {
                $query->where('status', $request->status);
            }
        }

        $licenses = $query->paginate(20)->withQueryString();
        $sites = Site::orderBy('title')->get(['id', 'title', 'domain']);
        $modules = Module::where('type', Module::TYPE_PAID)->orderBy('title')->get(['id', 'title', 'slug']);

        return view('admin.module-licenses.index', compact('licenses', 'sites', 'modules'));
    }

    public function revoke(ModuleLicense $license)
    {
        app(ModuleLicenseService::class)->revoke($license, request()->user());
        return back()->with('success', 'لایسنس لغو شد.');
    }

    public function grant(Request $request)
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
            'module_id' => 'required|exists:modules,id',
            'expires_at' => 'nullable|date',
        ]);

        $site = Site::findOrFail($request->site_id);
        $module = Module::assignableToSite()->find($request->module_id);
        if (! $module || $module->type !== Module::TYPE_PAID) {
            return back()->with('error', 'ماژول انتخاب‌شده معتبر نیست.');
        }

        if (app(ModuleLicenseService::class)->hasValidLicense($site, $module->id)) {
            return back()->with('info', 'این سایت از قبل لایسنس معتبر برای این ماژول دارد.');
        }

        $expiresAt = $request->filled('expires_at')
            ? \Carbon\Carbon::parse($request->expires_at)
            : null;

        app(ModuleLicenseService::class)->grantByAdmin($site, $module->id, $request->user(), $expiresAt);
        app(ModulePurchaseService::class)->grantByAdmin($site, $module->id, $request->user());

        // Wave 8.0b — ledger grant (idempotent؛ activity_logs فقط از EntitlementLedger)
        try {
            $license = ModuleLicense::query()
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->where('status', ModuleLicense::STATUS_ACTIVE)
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->first();
            $purchase = ModulePurchase::query()
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->orderByDesc('id')
                ->first();
            app(EntitlementLedger::class)->grant($site, $module, ModuleEntitlement::SOURCE_ADMIN_GRANT, [
                'granted_by' => $request->user()->id,
                'module_license_id' => $license?->id,
                'module_purchase_id' => $purchase?->id,
                'metadata' => ['path' => 'admin_module_license_grant'],
            ]);
        } catch (\Throwable) {
            // best-effort: اعطای لایسنس اصلی نباید به خاطر ledger شکست بخورد
        }

        return back()->with('success', 'لایسنس اعطا شد.');
    }
}
