@extends('layouts.admin')

@section('header', 'قالب اعلان خوش‌آمدگویی جدید')

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.notification-templates.index') }}" class="text-indigo-600 hover:underline">قالب‌های خوش‌آمدگویی</a>
        → جدید
    </p>

    <div class="card p-4 shadow-sm rounded-xl">
        <form method="POST" action="{{ route('admin.notification-templates.store') }}">
            @csrf
            @include('admin.notification-templates._form', ['template' => null])
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">ذخیره قالب</button>
                <a href="{{ route('admin.notification-templates.index') }}" class="btn btn-outline-secondary me-2">انصراف</a>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('template-image-file');
    var pathInput = document.getElementById('template-image-path');
    var uploadBtn = document.getElementById('template-image-upload-btn');
    if (fileInput && pathInput && uploadBtn) {
        uploadBtn.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var fd = new FormData();
            fd.append('image', this.files[0]);
            fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'در حال آپلود...';
            fetch('{{ route("admin.notification-templates.upload-image") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) {
                    if (!r.ok) return r.json().then(function(d) { throw new Error(d.message || 'خطا در آپلود'); });
                    return r.json();
                })
                .then(function(d) {
                    if (d.path) {
                        pathInput.value = d.path;
                        var preview = document.getElementById('template-image-preview');
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
