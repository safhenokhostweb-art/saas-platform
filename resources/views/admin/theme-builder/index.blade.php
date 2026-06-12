@extends('layouts.admin')

@section('header', 'ساختار تم (Theme Builder)')

@section('content')
<div class="flex flex-wrap gap-2 items-center justify-between">
    <div class="flex flex-wrap gap-2 items-center">
        <a href="{{ route('admin.theme-builder.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            ایجاد تم جدید
        </a>
        <a href="{{ route('admin.theme-builder.create', ['site_id' => 0]) }}" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">
            قالب پلتفرم
        </a>
        <a href="{{ route('admin.theme-builder.ai-form') }}"
           class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-4 py-2 rounded hover:from-indigo-600 hover:to-purple-700 font-medium shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            ساخت با AI
        </a>
        <form action="{{ route('admin.theme-builder.import') }}" method="post" enctype="multipart/form-data" class="inline-flex items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".json" class="form-control w-auto max-w-[200px]" required>
            <input type="hidden" name="site_id" value="{{ $siteId ?? 0 }}">
            <button type="submit" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">وارد کردن تم</button>
        </form>
    </div>
    <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-gray-600">سایت:</label>
        <select name="site_id" onchange="this.form.submit()" class="form-select w-48">
            <option value="0" {{ ($siteId ?? 0) == 0 ? 'selected' : '' }}>همه / قالب‌های پلتفرم</option>
            @foreach($sites as $s)
                <option value="{{ $s->id }}" {{ ($siteId ?? 0) == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نام / شناسه</th>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">آخرین تغییر</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($themes as $theme)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        <span class="font-medium">{{ $theme->name }}</span>
                        <span class="text-gray-500 block text-xs">{{ $theme->slug }}</span>
                    </td>
                    <td class="p-3">
                        @if($theme->site_id)
                            <a href="{{ route('admin.sites.edit', $theme->site) }}" class="text-indigo-600">{{ $theme->site->title ?? '—' }}</a>
                        @else
                            <span class="text-gray-400">قالب پلتفرم</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($theme->isPublished())
                            <span class="text-green-600 font-medium">منتشر شده</span>
                        @else
                            <span class="text-amber-600">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="p-3 text-gray-600">{{ $theme->updated_at?->format('Y/m/d H:i') }}</td>
                    <td class="p-3">
                        <a href="{{ route('admin.theme-builder.canvas', $theme) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition ml-2">
                            🎨 قالب‌ساز بصری
                        </a>
                        <a href="{{ route('admin.theme-builder.edit', $theme) }}" class="text-indigo-600 hover:underline ml-2">ویرایش پایه</a>
                        <a href="{{ route('admin.theme-builder.export', $theme) }}" class="text-indigo-600 hover:underline ml-2">JSON</a>
                        <a href="{{ route('admin.theme-builder.clone-form', $theme) }}" class="text-indigo-600 hover:underline ml-2">کپی</a>
                        <form action="{{ route('admin.theme-builder.destroy', $theme) }}" method="post" class="inline" onsubmit="return confirm('حذف این تم؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline ml-2">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-6 text-center text-gray-500">تمی یافت نشد. یک تم جدید ایجاد کنید یا از فایل JSON وارد کنید.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $themes->links() }}</div>
</div>
@endsection
