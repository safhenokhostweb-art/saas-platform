@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'دسته‌بندی‌های آموزشی')

@section('content')
<div class="space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">دسته‌بندی‌های آموزشی</h1>
        <a href="{{ route('admin.education.index') }}" class="text-sm text-cyan-600 hover:underline">← مرکز آموزش</a>
    </div>

    {{-- Add Category --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-gray-700">افزودن دسته‌بندی</h2>
        <form method="POST" action="{{ route('admin.education.categories.store') }}" class="flex flex-wrap gap-3">
            @csrf
            <input type="text" name="name" placeholder="نام دسته‌بندی" required
                   class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none w-48">
            <input type="text" name="slug" placeholder="slug (اختیاری)"
                   class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none w-40">
            <select name="content_type" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                <option value="mixed">ترکیبی</option>
                <option value="articles">مقالات</option>
                <option value="walkthroughs">راهنماها</option>
            </select>
            <select name="visibility" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none">
                <option value="authenticated">احراز هویت‌شده</option>
                <option value="public">عمومی</option>
                <option value="platform_only">فقط پلتفرم</option>
                <option value="role_based">مبتنی بر نقش</option>
            </select>
            <button type="submit" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">افزودن</button>
        </form>
    </div>

    {{-- Category Tree --}}
    <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">نام</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">نوع</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">دید</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">مقالات</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">وضعیت</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($categories as $cat)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-900">
                        {{ $cat->name }}
                        @foreach($cat->children as $child)
                        <div class="mt-1 pr-4 text-xs text-gray-500">↳ {{ $child->name }}</div>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $cat->content_type }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $cat->visibility }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $cat->articles->count() }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs {{ $cat->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $cat->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.education.categories.destroy', $cat) }}"
                              onsubmit="return confirm('حذف این دسته؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">دسته‌بندی‌ای یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
