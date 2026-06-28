@extends('layouts.admin')

@section('header', 'وارد کردن کلید SSH')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">وارد کردن کلید خصوصی SSH</h1>
        <p class="text-sm text-gray-500">کلید با رمزگذاری AES-256-GCM ذخیره می‌شود — هرگز در معرض دید قرار نمی‌گیرد</p>
    </div>
</div>

<div class="max-w-xl">

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
            <ul class="text-red-700 text-sm list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.import.store') }}">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">

            <div>
                <label class="form-label">نام کلید <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label') }}"
                       placeholder="مثال: کلید موجود سرور قدیمی"
                       class="form-control @error('label') border-red-400 @enderror">
                @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="form-label">کلید خصوصی SSH <span class="text-red-500">*</span></label>
                <textarea name="private_key" rows="9"
                    placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"
                    class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror">{{ old('private_key') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">فرمت‌های پشتیبانی: OpenSSH, PEM (RSA/EC)</p>
                @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="form-label">توضیح <span class="text-gray-400 text-xs font-normal">اختیاری</span></label>
                <input type="text" name="comment" value="{{ old('comment') }}"
                       class="form-control font-mono text-xs">
            </div>

            @if($servers->isNotEmpty())
            <div>
                <label class="form-label">مقصد <span class="text-gray-400 text-xs font-normal">اختیاری</span></label>
                <select name="target_id" class="form-control">
                    <option value="">انتخاب اختیاری</option>
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}" {{ old('target_id') == $server->id ? 'selected' : '' }}>{{ $server->label }}</option>
                    @endforeach
                </select>
            </div>
            @endif

        </div>

        <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-800 flex gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            کلید خصوصی باید از منبع امن وارد شود. این اطلاعات روی HTTPS ارسال و با AES-256-GCM ذخیره می‌شوند.
        </div>

        <div class="mt-5 flex gap-3">
            <button type="submit" class="btn btn-primary">وارد کردن کلید</button>
            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>

    </form>
</div>

@endsection
