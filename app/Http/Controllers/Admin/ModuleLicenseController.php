<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Services\ModuleLicenseGrantService;
use App\Services\ModuleLicenseService;
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
        $expiresAt = $request->filled('expires_at')
            ? \Carbon\Carbon::parse($request->expires_at)
            : null;

        $outcome = app(ModuleLicenseGrantService::class)
            ->grant($site, (int) $request->module_id, $request->user(), $expiresAt);

        if ($outcome === ModuleLicenseGrantService::INVALID_MODULE) {
            return back()->with('error', 'ماژول انتخاب‌شده معتبر نیست.');
        }
        if ($outcome === ModuleLicenseGrantService::ALREADY_LICENSED) {
            return back()->with('info', 'این سایت از قبل لایسنس معتبر برای این ماژول دارد.');
        }

        return back()->with('success', 'لایسنس اعطا شد.');
    }
}
