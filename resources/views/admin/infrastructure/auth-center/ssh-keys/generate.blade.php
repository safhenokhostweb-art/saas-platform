@extends('layouts.admin')

@section('header', 'تولید کلید SSH')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">تولید کلید SSH جدید</h1>
        <p class="text-sm text-gray-500">کلید به‌صورت رمزگذاری‌شده (AES-256-GCM) ذخیره می‌شود — کلید خصوصی هرگز نمایش داده نمی‌شود</p>
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

    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.generate.store') }}">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">

            {{-- Key type selector --}}
            <div x-data="{ keyType: '{{ old('key_type', 'ed25519') }}' }">
                <label class="form-label">نوع کلید <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3 mt-2">

                    <label :class="keyType === 'ed25519' ? 'ring-2 ring-indigo-500 border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                           class="relative border-2 rounded-xl p-4 cursor-pointer transition-all">
                        <input type="radio" name="key_type" value="ed25519" x-model="keyType" class="sr-only">
                        <div class="font-semibold text-sm text-gray-900">Ed25519</div>
                        <div class="text-xs text-gray-500 mt-0.5">پیشنهادی — سریع‌تر، امن‌تر، کلید کوچک‌تر</div>
                        <div x-show="keyType === 'ed25519'" class="absolute top-2 left-2 w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                    </label>

                    <label :class="keyType === 'rsa4096' ? 'ring-2 ring-indigo-500 border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                           class="relative border-2 rounded-xl p-4 cursor-pointer transition-all">
                        <input type="radio" name="key_type" value="rsa4096" x-model="keyType" class="sr-only">
                        <div class="font-semibold text-sm text-gray-900">RSA-4096</div>
                        <div class="text-xs text-gray-500 mt-0.5">سازگاری بیشتر با سرورهای قدیمی</div>
                        <div x-show="keyType === 'rsa4096'" class="absolute top-2 left-2 w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                    </label>

                </div>
            </div>

            {{-- Label --}}
            <div>
                <label class="form-label">نام کلید <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label') }}"
                       placeholder="مثال: VPS آلمان — کلید اصلی ۱۴۰۳"
                       class="form-control @error('label') border-red-400 @enderror">
                @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Comment --}}
            <div>
                <label class="form-label">توضیح (comment) <span class="text-gray-400 text-xs font-normal">اختیاری</span></label>
                <input type="text" name="comment" value="{{ old('comment', 'saas-platform-' . date('Y-m-d')) }}"
                       class="form-control font-mono text-xs @error('comment') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1">به انتهای کلید عمومی اضافه می‌شود — معمولاً ایمیل یا نام سرور</p>
            </div>

            {{-- Target --}}
            @if($servers->isNotEmpty())
            <div>
                <label class="form-label">مقصد استقرار <span class="text-gray-400 text-xs font-normal">اختیاری</span></label>
                <select name="target_id" class="form-control @error('target_id') border-red-400 @enderror">
                    <option value="">انتخاب اختیاری — می‌توان بعداً انتساب داد</option>
                    @foreach($servers->where('type', 'ssh') as $server)
                        <option value="{{ $server->id }}" {{ old('target_id') == $server->id ? 'selected' : '' }}>{{ $server->label }} — {{ $server->host }}</option>
                    @endforeach
                </select>
                @error('target_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

        </div>

        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 flex gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            کلید خصوصی پس از تولید هرگز نمایش داده نخواهد شد. برای نصب روی سرور از دکمه «نصب روی سرور» استفاده کنید.
        </div>

        <div class="mt-5 flex gap-3">
            <button type="submit" class="btn btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                تولید کلید SSH
            </button>
            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>

    </form>
</div>

@endsection
