@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'یادداشت‌های انتشار')

@section('content')
<div class="space-y-5" dir="rtl">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">یادداشت‌های انتشار</h1>
        <a href="{{ route('admin.education.release-notes.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">
            + یادداشت جدید
        </a>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">عنوان</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">نسخه</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">نوع</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">ماژول</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">مرتبط‌با‌ویرایش</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">وضعیت</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $note)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $note->title }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $note->version_number }}</td>
                    <td class="px-4 py-3">
                        @php
                            $typeColors = ['feature'=>'bg-cyan-50 text-cyan-700','fix'=>'bg-emerald-50 text-emerald-700',
                                           'improvement'=>'bg-blue-50 text-blue-700','breaking'=>'bg-red-50 text-red-700',
                                           'security'=>'bg-orange-50 text-orange-700'];
                            $tc = $typeColors[$note->release_type] ?? 'bg-gray-100 text-gray-600';
                        @endphp
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs {{ $tc }}">{{ $note->release_type }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $note->module_slug ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($note->revision_id)
                        <span class="inline-flex rounded-md bg-violet-50 text-violet-700 px-2 py-0.5">rev #{{ $note->revision_id }}</span>
                        @else —
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs {{ $note->is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $note->is_published ? 'منتشر' : 'پیش‌نویس' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.education.release-notes.edit', $note) }}" class="text-xs text-cyan-600 hover:underline">ویرایش</a>
                            <form method="POST" action="{{ route('admin.education.release-notes.destroy', $note) }}"
                                  onsubmit="return confirm('حذف این یادداشت؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:underline">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">یادداشت انتشاری ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">{{ $items->links() }}</div>
</div>
@endsection
