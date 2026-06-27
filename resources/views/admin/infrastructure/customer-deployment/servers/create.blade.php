@extends('layouts.admin')

@section('header', 'افزودن سرور جدید')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">افزودن سرور SSH</h1>
        <p class="text-sm text-gray-500">اطلاعات اتصال SSH به سرور راه‌دور</p>
    </div>
</div>

<div class="max-w-2xl" x-data="serverForm()">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
                <p class="text-red-800 text-sm font-semibold mb-1">خطاهای اعتبارسنجی:</p>
                <ul class="text-red-700 text-sm list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.store') }}">
            @csrf

            {{-- Label --}}
            <div class="mb-5">
                <label class="form-label">برچسب سرور <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label') }}" placeholder="مثال: سرور آلمان ۱" class="form-control @error('label') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1">نامی که در لیست سرورها نمایش داده می‌شود</p>
                @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Type (hidden, always SSH for now) --}}
            <input type="hidden" name="type" value="ssh">

            <div class="grid grid-cols-2 gap-4 mb-5">
                {{-- Host --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="form-label">آدرس هاست / IP <span class="text-red-500">*</span></label>
                    <input type="text" name="host" value="{{ old('host') }}" placeholder="192.168.1.1 یا server.example.com" class="form-control font-mono @error('host') border-red-400 @enderror">
                    @error('host')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                {{-- Port --}}
                <div>
                    <label class="form-label">پورت SSH <span class="text-red-500">*</span></label>
                    <input type="number" name="port" value="{{ old('port', 22) }}" min="1" max="65535" class="form-control font-mono @error('port') border-red-400 @enderror">
                    @error('port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Username --}}
            <div class="mb-5">
                <label class="form-label">نام کاربری SSH <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username', 'root') }}" placeholder="root" class="form-control font-mono @error('username') border-red-400 @enderror">
                @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Auth Method --}}
            <div class="mb-5">
                <label class="form-label">روش احراز هویت <span class="text-red-500">*</span></label>
                <div class="flex gap-3 mt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="auth_method" value="key" x-model="authMethod" {{ old('auth_method', 'key') === 'key' ? 'checked' : '' }} class="text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">کلید SSH (Private Key)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="auth_method" value="password" x-model="authMethod" {{ old('auth_method') === 'password' ? 'checked' : '' }} class="text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">رمز عبور</span>
                    </label>
                </div>
            </div>

            {{-- Private Key --}}
            <div class="mb-5" x-show="authMethod === 'key'" x-cloak>
                <label class="form-label">کلید خصوصی SSH <span class="text-red-500">*</span></label>
                <textarea name="private_key" rows="8" placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----" class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror">{{ old('private_key') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">
                    <svg class="w-3 h-3 inline-block text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    کلید خصوصی با رمزگذاری AES-256-GCM ذخیره می‌شود و هرگز نمایش داده نمی‌شود
                </p>
                @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Password --}}
            <div class="mb-5" x-show="authMethod === 'password'" x-cloak>
                <label class="form-label">رمز عبور SSH <span class="text-red-500">*</span></label>
                <input type="password" name="password" placeholder="رمز عبور SSH" class="form-control @error('password') border-red-400 @enderror" autocomplete="new-password">
                <p class="text-xs text-gray-400 mt-1">
                    <svg class="w-3 h-3 inline-block text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    رمز عبور با رمزگذاری AES-256-GCM ذخیره می‌شود و هرگز نمایش داده نمی‌شود
                </p>
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Security notice --}}
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6 text-xs text-amber-800 flex gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>اطلاعات اتصال شما رمزگذاری شده ذخیره می‌شود. دسترسی به سرور فقط برای عملیات نصب مشتریان استفاده می‌شود.</span>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">ذخیره سرور</button>
                <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function serverForm() {
    return {
        authMethod: '{{ old('auth_method', 'key') }}',
    };
}
</script>
@endpush

@endsection
