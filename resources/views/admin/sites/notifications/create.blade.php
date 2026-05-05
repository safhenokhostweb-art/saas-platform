@extends('layouts.admin')

@section('header', 'اعلان جدید — ' . $site->title)

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">{{ $site->title }}</a>
        →
        <a href="{{ route('admin.sites.notifications.index', $site) }}" class="text-indigo-600 hover:underline">اعلان‌ها</a>
        → جدید
    </p>

    <div class="card p-4 shadow-sm rounded-xl">
        <form method="POST" action="{{ route('admin.sites.notifications.store', $site) }}">
            @csrf
            @include('admin.sites.notifications._form', ['notification' => null])
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">ذخیره اعلان</button>
                <a href="{{ route('admin.sites.notifications.index', $site) }}" class="btn btn-outline-secondary me-2">انصراف</a>
                <a href="{{ route('admin.sites.notifications.create-bulk') }}" class="btn btn-outline-secondary me-2">ارسال به چند سایت</a>
            </div>
        </form>
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
    if (typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({ format: 'YYYY/MM/DD', persianDigit: true, initialValueType: 'persian' });
    }
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
