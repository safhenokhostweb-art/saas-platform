@extends('layouts.admin')

@section('header', 'ارسال اعلان به چند سایت')

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="{{ route('admin.sites.notifications.create-bulk') }}" class="text-indigo-600 hover:underline">اعلان به چند سایت</a>
    </p>

    <div class="card p-4 shadow-sm rounded-xl">
        <form method="POST" action="{{ route('admin.sites.notifications.store-bulk') }}" id="notif-bulk-form">
            @csrf

            <div class="mb-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <h5 class="font-bold mb-3">سایت‌های هدف *</h5>
                <p class="text-sm text-gray-600 mb-3">یک یا چند سایت را انتخاب کنید. اعلان با همان محتوا برای همهٔ سایت‌های انتخاب‌شده ارسال می‌شود.</p>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="select-all-sites" class="rounded border-gray-300">
                        <span class="font-medium">همه</span>
                    </label>
                    <span class="text-gray-400">|</span>
                    @foreach($sites as $s)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="site_ids[]" value="{{ $s->id }}" class="site-checkbox rounded border-gray-300" {{ in_array($s->id, old('site_ids', [])) ? 'checked' : '' }}>
                            <span>{{ $s->title }}</span>
                        </label>
                    @endforeach
                </div>
                @error('site_ids')
                    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                @enderror
            </div>

            @include('admin.sites.notifications._form', ['notification' => null])

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">ارسال اعلان به سایت‌های انتخاب‌شده</button>
                <a href="{{ route('admin.sites.index') }}" class="btn btn-outline-secondary me-2">انصراف</a>
            </div>
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
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian' });
    }
    var selectAll = document.getElementById('select-all-sites');
    var checkboxes = document.querySelectorAll('.site-checkbox');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
        });
    }
    document.getElementById('notif-bulk-form')?.addEventListener('submit', function(e) {
        var checked = document.querySelectorAll('.site-checkbox:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('حداقل یک سایت را انتخاب کنید.');
        }
    });
    var fileInput = document.getElementById('notif-image-file');
    var pathInput = document.getElementById('notif-image-path');
    var uploadBtn = document.getElementById('notif-image-upload-btn');
    if (fileInput && pathInput && uploadBtn) {
        uploadBtn.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var fd = new FormData();
            fd.append('image', this.files[0]);
            fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'در حال آپلود...';
            fetch('{{ route("admin.notifications.upload-image") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) {
                    if (!r.ok) return r.json().then(function(d) { throw new Error(d.message || 'خطا در آپلود'); });
                    return r.json();
                })
                .then(function(d) {
                    if (d.path) {
                        pathInput.value = d.path;
                        var preview = document.getElementById('notif-image-preview');
                        if (preview) { preview.textContent = '✓ ذخیره شد: ' + d.path; preview.style.color = '#15803d'; preview.style.fontWeight = '600'; }
                        alert('تصویر با موفقیت ذخیره شد.');
                    }
                })
                .catch(function(e) { alert(e.message || 'خطا در آپلود'); })
                .finally(function() { uploadBtn.disabled = false; uploadBtn.textContent = 'انتخاب تصویر'; fileInput.value = ''; });
        });
    }
});
</script>
@endpush
@endsection
