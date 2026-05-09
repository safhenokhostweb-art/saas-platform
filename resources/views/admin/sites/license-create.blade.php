@extends('layouts.admin')

@section('header', 'صدور لایسنس — ' . $site->title)

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">{{ $site->title }}</a>
        → صدور لایسنس
    </p>

    @if ($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            <ul class="list-disc list-inside text-right mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card p-4 shadow-sm rounded-xl">
        <h5 class="font-bold mb-3">صدور لایسنس جدید</h5>
        <p class="text-sm text-gray-600 mb-4">کلید لایسنس فقط یک‌بار نمایش داده می‌شود. آن را در محل امن ذخیره کرده و به مشتری تحویل دهید.</p>
        <form method="POST" action="{{ route('admin.sites.license.store', $site) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">دامنهٔ مجاز (اختیاری؛ خالی = هر دامنه)</label>
                <input type="text" name="domain" class="form-control" placeholder="example.ir" value="{{ old('domain', $site->domain) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">تاریخ انقضا (شمسی، اختیاری)</label>
                <x-persian-date-input name="expires_at" placeholder="۱۴۰۳/۱۲/۲۹" :value="old('expires_at')" class="form-control" />
            </div>
            <button type="submit" class="btn btn-primary">صدور لایسنس</button>
            <a href="{{ route('admin.sites.edit', $site) }}" class="btn btn-outline-secondary me-2">انصراف</a>
        </form>
    </div>
</div>
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}"></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
@endpush
@endsection
