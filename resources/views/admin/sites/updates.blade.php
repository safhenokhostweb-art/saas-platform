@extends('layouts.admin')

@section('header', 'توزیع بروزرسانی — ' . $site->title)

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">{{ $site->title }}</a>
        → توزیع بروزرسانی
    </p>

    <div class="card p-4 shadow-sm rounded-xl mb-4">
        <h5 class="font-bold mb-3">ثبت توزیع بروزرسانی جدید</h5>
        <form method="POST" action="{{ route('admin.sites.updates.store', $site) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">نسخه / برچسب</label>
                <input type="text" name="version_label" class="form-control" placeholder="مثال: 1.2.0 یا ۱۴۰۳/۰۷" value="{{ old('version_label') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">تاریخ توزیع (شمسی)</label>
                <x-persian-date-input name="distributed_at" :value="old('distributed_at', \App\Helpers\Jalali::format(now()))" placeholder="۱۴۰۳/۰۷/۱۵" class="form-control" />
            </div>
            <div class="mb-3">
                <label class="form-label">یادداشت (اختیاری)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="توضیح کوتاه">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">ثبت توزیع</button>
        </form>
    </div>

    <h5 class="font-bold mb-2">تاریخچه توزیع بروزرسانی</h5>
    <div class="bg-white rounded shadow overflow-hidden">
        @if($site->siteUpdates->isEmpty())
            <p class="p-4 text-gray-500">هنوز بروزرسانی‌ای ثبت نشده است.</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-right">نسخه</th>
                        <th class="p-3 text-right">تاریخ توزیع</th>
                        <th class="p-3 text-right">یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($site->siteUpdates as $update)
                        <tr class="border-t">
                            <td class="p-3 font-medium">{{ $update->version_label }}</td>
                            <td class="p-3">{{ $update->distributed_at ? \App\Helpers\Jalali::formatFa($update->distributed_at) : '—' }}</td>
                            <td class="p-3 text-gray-600">{{ $update->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@push('head')
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
@endpush
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian' });
    }
});
</script>
@endpush
@endsection
