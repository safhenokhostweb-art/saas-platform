@extends('layouts.admin')

@section('header', 'اتصال فرم به سایت‌ها: ' . $form->title)

@section('content')
<div class="container py-4">
    <p class="mb-4">
        <a href="{{ route('admin.forms.preview', $form) }}" class="btn btn-primary inline-flex items-center gap-1" target="_blank">
            مشاهده فرم نهایی (پیش‌نمایش برای تست)
        </a>
        <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn-secondary inline-flex items-center gap-1 mr-2">ویرایش فرم</a>
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.forms.sites.update', $form) }}" class="card p-4">
        @csrf
        @method('PUT')

        @foreach($sites as $site)
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="sites[]"
                       value="{{ $site->id }}"
                       {{ in_array($site->id, $attached) ? 'checked' : '' }}>
                <label class="form-check-label">
                    {{ $site->title }} ({{ $site->domain }})
                </label>
            </div>
        @endforeach

        <button class="btn btn-primary w-100 mt-3">ذخیره اتصال‌ها</button>
    </form>
</div>
@endsection
