<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemeMarketplace;
use App\Models\ThemePurchaseOrder;
use Illuminate\Http\Request;

class ThemeMarketplaceAdminController extends Controller
{
    public function index()
    {
        $themes = Theme::with('marketplaceMeta')
            ->whereNull('site_id')
            ->orderByDesc('is_marketplace')
            ->orderBy('name')
            ->get();

        $totalLicenses = ThemeLicense::count();
        $totalRevenue  = ThemePurchaseOrder::where('status', ThemePurchaseOrder::STATUS_PAID)->sum('amount_rial');
        $pendingOrders = ThemePurchaseOrder::where('status', ThemePurchaseOrder::STATUS_PENDING)->count();

        return view('admin.theme-marketplace.index', compact('themes', 'totalLicenses', 'totalRevenue', 'pendingOrders'));
    }

    public function updateListing(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'is_marketplace'         => 'required|boolean',
            'marketplace_price'      => 'required_if:is_marketplace,true|integer|min:0',
            'marketplace_description'=> 'nullable|string|max:2000',
            'screenshots'            => 'nullable|array|max:6',
            'screenshots.*'          => 'nullable|string|max:500',
            'requirements'           => 'nullable|array',
            'requirements.*'         => 'nullable|string|max:100',
            'version_notes'          => 'nullable|string|max:500',
            'is_featured'            => 'nullable|boolean',
        ]);

        $theme->update([
            'is_marketplace'    => $data['is_marketplace'],
            'marketplace_price' => $data['marketplace_price'] ?? 0,
        ]);

        if ($data['is_marketplace']) {
            ThemeMarketplace::updateOrCreate(
                ['theme_id' => $theme->id],
                [
                    'marketplace_description' => $data['marketplace_description'] ?? null,
                    'screenshots'             => array_filter($data['screenshots'] ?? []),
                    'requirements'            => array_filter($data['requirements'] ?? []),
                    'version_notes'           => $data['version_notes'] ?? null,
                    'is_featured'             => $data['is_featured'] ?? false,
                ]
            );
        } else {
            ThemeMarketplace::where('theme_id', $theme->id)->delete();
        }

        return back()->with('success', 'اطلاعات مارکت‌پلیس ذخیره شد.');
    }

    public function toggleFeatured(Theme $theme)
    {
        $meta = ThemeMarketplace::where('theme_id', $theme->id)->first();
        if (!$meta) {
            return back()->with('error', 'قالب در مارکت‌پلیس نیست.');
        }

        $meta->update(['is_featured' => !$meta->is_featured]);

        return back()->with('success', 'وضعیت ویژه تغییر کرد.');
    }

    public function licenses()
    {
        $licenses = ThemeLicense::with(['theme', 'site'])
            ->orderByDesc('purchased_at')
            ->paginate(30);

        $orders = ThemePurchaseOrder::with(['theme', 'site'])
            ->whereIn('status', [ThemePurchaseOrder::STATUS_PAID, ThemePurchaseOrder::STATUS_PENDING])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.theme-marketplace.licenses', compact('licenses', 'orders'));
    }

    public function revokeLicense(ThemeLicense $license)
    {
        $license->delete();

        return back()->with('success', 'لایسنس لغو شد.');
    }

    public function grantLicense(Request $request)
    {
        $data = $request->validate([
            'site_id'  => 'required|exists:sites,id',
            'theme_id' => 'required|exists:themes,id',
        ]);

        ThemeLicense::grant((int) $data['site_id'], (int) $data['theme_id']);

        return back()->with('success', 'لایسنس دستی اعطا شد.');
    }
}
