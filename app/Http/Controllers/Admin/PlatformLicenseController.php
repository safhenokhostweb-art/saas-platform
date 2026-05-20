<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformLicense;
use Illuminate\Http\Request;

/**
 * مدیریت مرکزی لایسنس‌های صادرشده (سایت مشتری). امکان غیرفعال کردن و فعال‌سازی مجدد.
 */
class PlatformLicenseController extends Controller
{
    public function index(Request $request)
    {
        $query = PlatformLicense::with('site.customer')->latest('issued_at');

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereNull('revoked_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            } elseif ($request->status === 'expired') {
                $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
            } elseif ($request->status === 'revoked') {
                $query->whereNotNull('revoked_at');
            }
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        $licenses = $query->paginate(25)->withQueryString();

        return view('admin.licenses.index', compact('licenses'));
    }

    /**
     * غیرفعال کردن (ابطال) لایسنس — پس از آن سایت با این کلید دیگر معتبر شناخته نمی‌شود.
     */
    public function revoke(Request $request, PlatformLicense $platform_license)
    {
        if ($platform_license->isRevoked()) {
            return redirect()->route('admin.licenses.index')->with('info', 'این لایسنس قبلاً ابطال شده است.');
        }

        $platform_license->update(['revoked_at' => now()]);

        $siteTitle = $platform_license->site?->title ?? $platform_license->site_id;
        return redirect()->route('admin.licenses.index', $request->only('status', 'site_id'))
            ->with('success', "لایسنس سایت «{$siteTitle}» غیرفعال (ابطال) شد.");
    }

    /**
     * فعال‌سازی مجدد لایسنس ابطال‌شده.
     */
    public function reactivate(Request $request, PlatformLicense $platform_license)
    {
        if (!$platform_license->isRevoked()) {
            return redirect()->route('admin.licenses.index')->with('info', 'این لایسنس فعال است.');
        }

        $platform_license->update(['revoked_at' => null]);

        $siteTitle = $platform_license->site?->title ?? $platform_license->site_id;
        return redirect()->route('admin.licenses.index', $request->only('status', 'site_id'))
            ->with('success', "لایسنس سایت «{$siteTitle}» دوباره فعال شد.");
    }

    /**
     * حذف دائمی رکورد لایسنس (فقط برای لایسنس‌های ابطال‌شده؛ ارجاع فاکتورها صفر می‌شود).
     */
    public function destroy(Request $request, PlatformLicense $platform_license)
    {
        if (!$platform_license->isRevoked()) {
            return redirect()->route('admin.licenses.index')
                ->with('error', 'فقط لایسنس‌های ابطال‌شده را می‌توان حذف کرد. ابتدا لایسنس را غیرفعال کنید.');
        }

        \App\Models\InvoiceItem::where('platform_license_id', $platform_license->id)->update(['platform_license_id' => null]);
        $platform_license->delete();

        return redirect()->route('admin.licenses.index', $request->only('status', 'site_id'))
            ->with('success', 'لایسنس حذف شد.');
    }
}
