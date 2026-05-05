<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Site $site)
    {
        $menus = $site->menus()->withCount('allItems')->orderBy('location')->orderBy('order')->get();
        return view('admin.menus.index', compact('site', 'menus'));
    }

    public function create(Site $site)
    {
        return view('admin.menus.create', compact('site'));
    }

    public function store(Request $request, Site $site)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|in:header,footer,sidebar',
        ]);
        $maxOrder = $site->menus()->where('location', $validated['location'])->max('order') ?? 0;
        $site->menus()->create(array_merge($validated, ['order' => $maxOrder + 1]));
        return redirect()->route('admin.sites.menus.index', $site)
            ->with('success', 'منو ایجاد شد.');
    }

    public function edit(Site $site, Menu $menu)
    {
        if ($menu->site_id !== $site->id) {
            abort(404);
        }
        $menu->load(['items' => fn ($q) => $q->with('children')]);
        return view('admin.menus.edit', compact('site', 'menu'));
    }

    public function update(Request $request, Site $site, Menu $menu)
    {
        if ($menu->site_id !== $site->id) {
            abort(404);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|in:header,footer,sidebar',
        ]);
        $menu->update($validated);
        return redirect()->route('admin.sites.menus.index', $site)
            ->with('success', 'منو به‌روز شد.');
    }

    public function destroy(Site $site, Menu $menu)
    {
        if ($menu->site_id !== $site->id) {
            abort(404);
        }
        $menu->delete();
        return redirect()->route('admin.sites.menus.index', $site)
            ->with('success', 'منو حذف شد.');
    }

    public function storeItem(Request $request, Site $site, Menu $menu)
    {
        if ($menu->site_id !== $site->id) {
            abort(404);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:500',
            'type' => 'required|in:link,page,post,product,category,custom',
            'target' => 'in:_self,_blank',
            'parent_id' => 'nullable|exists:menu_items,id',
        ]);
        $validated['target'] = $validated['target'] ?? '_self';
        $maxOrder = $menu->allItems()->max('order') ?? 0;
        $menu->allItems()->create(array_merge($validated, ['order' => $maxOrder + 1, 'is_active' => true]));
        return back()->with('success', 'آیتم منو اضافه شد.');
    }

    public function updateItem(Request $request, Site $site, Menu $menu, MenuItem $item)
    {
        if ($menu->site_id !== $site->id || $item->menu_id !== $menu->id) {
            abort(404);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:500',
            'type' => 'required|in:link,page,post,product,category,custom',
            'target' => 'in:_self,_blank',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $item->update($validated);
        return back()->with('success', 'آیتم منو به‌روز شد.');
    }

    public function destroyItem(Site $site, Menu $menu, MenuItem $item)
    {
        if ($menu->site_id !== $site->id || $item->menu_id !== $menu->id) {
            abort(404);
        }
        $item->delete();
        return back()->with('success', 'آیتم منو حذف شد.');
    }

    public function reorderItems(Request $request, Site $site, Menu $menu)
    {
        if ($menu->site_id !== $site->id) {
            abort(404);
        }
        $order = $request->input('order', []);
        foreach ($order as $i => $id) {
            MenuItem::where('menu_id', $menu->id)->where('id', $id)->update(['order' => $i + 1]);
        }
        return response()->json(['success' => true]);
    }
}
