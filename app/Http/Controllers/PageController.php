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

        return view('page.show', compact('page'));
    }
}
