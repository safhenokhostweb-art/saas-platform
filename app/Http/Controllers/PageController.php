<?php
namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $slug = trim($slug);
        $page = Page::where('is_active', true)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)
                    ->orWhere('slug', Str::slug($slug));
            })
            ->first();

        if (!$page) {
            abort(404, 'صفحه‌ای با این آدرس یافت نشد. اسلاگ را در پنل مدیریت (صفحات ثابت) بررسی کنید.');
        }

        // `page/{slug}` is a public, indexable route (routes/web.php:156).
        // It renders through `layouts.app`, which owns head metadata for every
        // surface. This controller used to hand the view a `seoRobots` value
        // for a `partials.meta-robots` include the layout did not have, so the
        // directive was inert and the page shipped the layout's platform-admin
        // `noindex, nofollow`. The directive now comes from the view's own
        // MetaContext::TENANT_PUBLIC resolution, which is `index, follow` by
        // definition and honours an explicit per-page override.
        return view('page.show', compact('page'));
    }
}
