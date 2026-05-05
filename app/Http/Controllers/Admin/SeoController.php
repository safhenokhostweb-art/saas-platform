<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPageScore;
use App\Models\SeoRedirect;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * داشبورد سئو و مدیر ریدایرکت برای هر سایت (ماژول سئو پیشرفته).
 */
class SeoController extends Controller
{
    public function index(Site $site)
    {
        $redirects = $site->seoRedirects()->orderBy('from_url')->paginate(20);
        $avgScore = (int) $site->seoPageScores()->avg('score');
        $pagesScored = $site->seoPageScores()->count();
        return view('admin.seo.index', compact('site', 'redirects', 'avgScore', 'pagesScored'));
    }

    public function storeRedirect(Request $request, Site $site)
    {
        $validated = $request->validate([
            'from_url' => 'required|string|max:500',
            'to_url' => 'required|string|max:500',
            'status_code' => 'required|in:301,302',
        ]);
        $validated['from_url'] = '/' . ltrim($validated['from_url'], '/');
        $validated['to_url'] = '/' . ltrim($validated['to_url'], '/');
        $validated['site_id'] = $site->id;
        $validated['is_active'] = true;
        SeoRedirect::updateOrCreate(
            ['site_id' => $site->id, 'from_url' => $validated['from_url']],
            $validated
        );
        return back()->with('success', 'ریدایرکت ذخیره شد.');
    }

    public function updateRedirect(Request $request, Site $site, SeoRedirect $redirect)
    {
        if ($redirect->site_id !== $site->id) {
            abort(404);
        }
        $validated = $request->validate([
            'from_url' => 'required|string|max:500',
            'to_url' => 'required|string|max:500',
            'status_code' => 'required|in:301,302',
            'is_active' => 'boolean',
        ]);
        $validated['from_url'] = '/' . ltrim($validated['from_url'], '/');
        $validated['to_url'] = '/' . ltrim($validated['to_url'], '/');
        $validated['is_active'] = $request->boolean('is_active');
        $redirect->update($validated);
        return back()->with('success', 'ریدایرکت به‌روز شد.');
    }

    public function destroyRedirect(Site $site, SeoRedirect $redirect)
    {
        if ($redirect->site_id !== $site->id) {
            abort(404);
        }
        $redirect->delete();
        return back()->with('success', 'ریدایرکت حذف شد.');
    }
}
