@extends('layouts.admin')

@section('header', 'صفحات ثابت و راهنما')

@section('content')

@php
    $menuPosition = \App\Models\Setting::get('page_menu_position', 'top');
@endphp
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-gray-600 text-sm">موقعیت نمایش فهرست صفحات در سایت:</span>
        <form method="POST" action="{{ route('admin.pages.menu-position') }}" class="inline-flex gap-2">
            @csrf
            <select name="position" onchange="this.form.submit()" class="form-select w-48">
                @foreach(\App\Models\Page::menuPositions() as $key => $label)
                    <option value="{{ $key }}" {{ $menuPosition === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">صفحه جدید</a>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <p class="text-gray-600 text-sm p-3 border-b bg-gray-50 border-gray-100">
        <span class="inline-flex items-center gap-1 ml-1" aria-hidden="true">⋮⋮</span>
        <strong>درگ اند دراپ:</strong> برای تغییر ترتیب نمایش صفحات در فهرست، روی ردیف را کشیده و رها کنید.
    </p>
    <table class="w-full text-right border-collapse">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
                <th class="p-3 w-10"></th>
                <th class="p-3 font-bold">#</th>
                <th class="p-3 font-bold">عنوان</th>
                <th class="p-3 font-bold">اسلاگ</th>
                <th class="p-3 font-bold">آیکن</th>
                <th class="p-3 font-bold">موقعیت منو</th>
                <th class="p-3 font-bold">ترتیب</th>
                <th class="p-3 font-bold">وضعیت</th>
                <th class="p-3 font-bold">عملیات</th>
            </tr>
        </thead>
        <tbody id="pages-sortable-tbody">
            @forelse($pages as $p)
                <tr class="border-b border-gray-100 hover:bg-gray-50 pages-sortable-row" data-page-id="{{ $p->id }}">
                    <td class="p-3 cursor-grab active:cursor-grabbing text-gray-400 select-none" title="کشیدن برای تغییر ترتیب"><span aria-hidden="true">⋮⋮</span></td>
                    <td class="p-3">{{ $p->id }}</td>
                    <td class="p-3 font-medium">{{ $p->title }}</td>
                    <td class="p-3 text-sm text-gray-600">{{ $p->slug }}</td>
                    <td class="p-3">
                        @if($p->icon_url)
                            <img src="{{ $p->icon_url }}" alt="" class="w-6 h-6 object-contain">
                        @else
                            @include('admin.pages.partials.icon-svg', ['name' => $p->icon_default ?? 'document', 'class' => 'w-6 h-6 inline-block text-gray-500'])
                        @endif
                    </td>
                    <td class="p-3 text-sm">{{ \App\Models\Page::menuPositions()[$p->menu_position] ?? $p->menu_position }}</td>
                    <td class="p-3">{{ $p->menu_order }}</td>
                    <td class="p-3">
                        @if($p->is_active)
                            <span class="text-green-600 text-sm">فعال</span>
                        @else
                            <span class="text-gray-400 text-sm">غیرفعال</span>
                        @endif
                    </td>
                    <td class="p-3">
                        <a href="{{ route('page.show', $p->slug) }}" target="_blank" class="text-blue-600 text-sm ml-2">مشاهده</a>
                        <a href="{{ route('admin.pages.edit', $p) }}" class="text-blue-600 text-sm ml-2">ویرایش</a>
                        <form action="{{ route('admin.pages.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('حذف شود؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 text-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="p-6 text-center text-gray-500">صفحه‌ای وجود ندارد. <a href="{{ route('admin.pages.create') }}" class="text-blue-600">اولین صفحه را بسازید</a>.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.pages.partials.pages-index-guide')

@push('scripts')
{{-- SortableJS comes from the Vite bundle (resources/js/app.js exposes
     window.Sortable), which layouts/admin.blade.php already loads. The old
     cdn.jsdelivr.net tag here was a duplicate that the Content-Security-Policy
     blocked in production, producing a CSP violation on every page load. The
     usage below is already guarded by `typeof Sortable !== 'undefined'`, so
     the bundled copy is sufficient and no CDN origin is needed. --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.getElementById('pages-sortable-tbody');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            animation: 150,
            handle: '.cursor-grab',
            onEnd: function() {
                var order = [];
                tbody.querySelectorAll('.pages-sortable-row[data-page-id]').forEach(function(tr) {
                    order.push(tr.getAttribute('data-page-id'));
                });
                if (order.length === 0) return;
                fetch('{{ route("admin.pages.reorder") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ order: order })
                }).then(function() { window.location.reload(); });
            }
        });
    }
});
</script>
@endpush

@endsection
