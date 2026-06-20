<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemeBundle;
use App\Models\ThemeBundleOrder;
use Illuminate\Http\Request;

class ThemeBundleAdminController extends Controller
{
    public function index()
    {
        $bundles  = ThemeBundle::orderByDesc('is_featured')->orderBy('name')->get();
        $totalRevenue = ThemeBundleOrder::where('status', ThemeBundleOrder::STATUS_PAID)->sum('amount_rial');
        return view('admin.theme-bundles.index', compact('bundles', 'totalRevenue'));
    }

    public function create()
    {
        $themes = Theme::whereNull('site_id')->where('is_marketplace', true)->orderBy('name')->get();
        return view('admin.theme-bundles.create', compact('themes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'required|string|max:100|unique:theme_bundles,slug|alpha_dash',
            'description' => 'nullable|string|max:2000',
            'thumbnail'   => 'nullable|url|max:500',
            'price'       => 'required|integer|min:0',
            'theme_ids'   => 'required|array|min:2',
            'theme_ids.*' => 'required|integer|exists:themes,id',
            'is_active'   => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        ThemeBundle::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'thumbnail'   => $data['thumbnail'] ?? null,
            'price'       => $data['price'],
            'theme_ids'   => $data['theme_ids'],
            'is_active'   => $data['is_active'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
        ]);

        return redirect()->route('admin.theme-bundles.index')
            ->with('success', "باندل «{$data['name']}» ایجاد شد.");
    }

    public function edit(ThemeBundle $themeBundle)
    {
        $themes = Theme::whereNull('site_id')->where('is_marketplace', true)->orderBy('name')->get();
        return view('admin.theme-bundles.edit', compact('themeBundle', 'themes'));
    }

    public function update(Request $request, ThemeBundle $themeBundle)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'thumbnail'   => 'nullable|url|max:500',
            'price'       => 'required|integer|min:0',
            'theme_ids'   => 'required|array|min:2',
            'theme_ids.*' => 'required|integer|exists:themes,id',
            'is_active'   => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        $themeBundle->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail'   => $data['thumbnail'] ?? null,
            'price'       => $data['price'],
            'theme_ids'   => $data['theme_ids'],
            'is_active'   => $data['is_active'] ?? false,
            'is_featured' => $data['is_featured'] ?? false,
        ]);

        return redirect()->route('admin.theme-bundles.index')
            ->with('success', "باندل «{$themeBundle->name}» به‌روز شد.");
    }

    public function destroy(ThemeBundle $themeBundle)
    {
        $themeBundle->delete();
        return back()->with('success', 'باندل حذف شد.');
    }

    public function orders()
    {
        $orders = ThemeBundleOrder::with(['bundle', 'site'])
            ->orderByDesc('created_at')
            ->paginate(30);
        return view('admin.theme-bundles.orders', compact('orders'));
    }
}
