@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'مقالات آموزشی')

@section('content')
<div class="space-y-5" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">مقالات آموزشی</h1>
            <p class="text-sm text-gray-500">مدیریت تمام مقالات، راهنماها و مستندات پلتفرم</p>
        </div>
        <a href="{{ route('admin.education.articles.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            مقاله جدید
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="search" name="search" value="{{ request('search') }}"
               placeholder="جستجو در عنوان مقالات…"
               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-cyan-400 focus:outline-none w-64 direction-rtl">

        <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-cyan-400 focus:outline-none">
            <option value="">همه وضعیت‌ها</option>
            <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>پیش‌نویس</option>
            <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>منتشرشده</option>
            <option value="archived"  {{ request('status') === 'archived'  ? 'selected' : '' }}>بایگانی</option>
        </select>

        <select name="category_id" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-cyan-400 focus:outline-none">
            <option value="">همه دسته‌بندی‌ها</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">اعمال فیلتر</button>
        <a href="{{ route('admin.education.articles.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">پاک‌کردن</a>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">عنوان</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">دسته‌بندی</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">نوع</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">وضعیت</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">بازدید</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">تاریخ</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($articles as $article)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.education.articles.show', $article) }}" class="font-medium text-gray-900 hover:text-cyan-700 line-clamp-1">{{ $article->title }}</a>
                        @if($article->module_slug)
                        <span class="mt-0.5 block text-xs text-gray-400">ماژول: {{ $article->module_slug }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $article->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $article->content_type }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $statusMap = ['draft' => ['label'=>'پیش‌نویس','class'=>'bg-amber-50 text-amber-700'],
                                          'published' => ['label'=>'منتشر','class'=>'bg-emerald-50 text-emerald-700'],
                                          'archived'  => ['label'=>'بایگانی','class'=>'bg-gray-100 text-gray-500']];
                            $s = $statusMap[$article->status] ?? ['label'=>$article->status,'class'=>'bg-gray-100 text-gray-600'];
                        @endphp
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $s['class'] }}">{{ $s['label'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ number_format($article->view_count) }}</td>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $article->created_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.education.articles.edit', $article) }}" class="text-xs text-cyan-600 hover:underline">ویرایش</a>
                            @if($article->status !== 'published')
                            <form method="POST" action="{{ route('admin.education.articles.publish', $article) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-emerald-600 hover:underline">انتشار</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('admin.education.articles.destroy', $article) }}" class="inline"
                                  onsubmit="return confirm('حذف این مقاله؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:underline">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">مقاله‌ای یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex justify-center">{{ $articles->withQueryString()->links() }}</div>

</div>
@endsection
