@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'راهنماهای گام‌به‌گام')

@section('content')
<div class="space-y-5" dir="rtl">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">راهنماهای گام‌به‌گام</h1>
        <a href="{{ route('admin.education.walkthroughs.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
            + راهنمای جدید
        </a>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">عنوان</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">trigger route</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">شرط</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">گام‌ها</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">وضعیت</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $wt)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $wt->title }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $wt->trigger_route ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $wt->trigger_condition }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $wt->steps_count ?? $wt->steps->count() }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs {{ $wt->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $wt->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.education.walkthroughs.edit', $wt) }}" class="text-xs text-cyan-600 hover:underline">ویرایش</a>
                            <form method="POST" action="{{ route('admin.education.walkthroughs.destroy', $wt) }}"
                                  onsubmit="return confirm('حذف این راهنما؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:underline">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">راهنمایی تعریف نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">{{ $items->links() }}</div>
</div>
@endsection
