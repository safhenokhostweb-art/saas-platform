@extends('layouts.admin')

@section('header', 'ویرایش سرور: ' . $server->label)

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">ویرایش سرور: <span class="text-indigo-700">{{ $server->label }}</span></h1>
        <p class="text-sm text-gray-500 font-mono">{{ $server->username }}@{{ $server->host }}:{{ $server->port }}</p>
    </div>
</div>

<div class="max-w-2xl" x-data="serverEditForm()">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
                <p class="text-red-800 text-sm font-semibold mb-1">خطاهای اعتبارسنجی:</p>
                <ul class="text-red-700 text-sm list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.update', $server) }}">
            @csrf @method('PUT')

            {{-- Label --}}
            <div class="mb-5">
                <label class="form-label">برچسب سرور <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label', $server->label) }}" class="form-control @error('label') border-red-400 @enderror">
                @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-5">
                {{-- Host --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="form-label">آدرس هاست / IP <span class="text-red-500">*</span></label>
                    <input type="text" name="host" value="{{ old('host', $server->host) }}" class="form-control font-mono @error('host') border-red-400 @enderror">
                    @error('host')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                {{-- Port --}}
                <div>
                    <label class="form-label">پورت SSH <span class="text-red-500">*</span></label>
                    <input type="number" name="port" value="{{ old('port', $server->port) }}" min="1" max="65535" class="form-control font-mono @error('port') border-red-400 @enderror">
                    @error('port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Username --}}
            <div class="mb-5">
                <label class="form-label">نام کاربری SSH <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username', $server->username) }}" class="form-control font-mono @error('username') border-red-400 @enderror">
                @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Auth Method --}}
            <div class="mb-5">
                <label class="form-label">روش احراز هویت <span class="text-red-500">*</span></label>
                <div class="flex gap-3 mt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="auth_method" value="key" x-model="authMethod" {{ old('auth_method', $server->auth_method) === 'key' ? 'checked' : '' }} class="text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">کلید SSH (Private Key)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="auth_method" value="password" x-model="authMethod" {{ old('auth_method', $server->auth_method) === 'password' ? 'checked' : '' }} class="text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">رمز عبور</span>
                    </label>
                </div>
            </div>

            {{-- Private Key --}}
            <div class="mb-5" x-show="authMethod === 'key'" x-cloak>
                <label class="form-label">کلید خصوصی SSH جدید</label>
                <textarea name="private_key" rows="8" placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----&#10;(خالی بگذارید تا کلید فعلی حفظ شود)" class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror"></textarea>
                <div class="flex items-center gap-2 mt-1">
                    <svg class="w-3 h-3 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    <p class="text-xs text-gray-400">کلید فعلی ذخیره‌شده است — فقط در صورت تغییر پر کنید</p>
                </div>
                @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Password --}}
            <div class="mb-5" x-show="authMethod === 'password'" x-cloak>
                <label class="form-label">رمز عبور SSH جدید</label>
                <input type="password" name="password" placeholder="(خالی بگذارید تا رمز فعلی حفظ شود)" class="form-control @error('password') border-red-400 @enderror" autocomplete="new-password">
                <div class="flex items-center gap-2 mt-1">
                    <svg class="w-3 h-3 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    <p class="text-xs text-gray-400">رمز فعلی ذخیره‌شده است — فقط در صورت تغییر پر کنید</p>
                </div>
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Server metadata --}}
            <div class="bg-gray-50 rounded-lg p-3 mb-5 text-xs text-gray-500 grid grid-cols-2 gap-2">
                <div>
                    <span class="font-medium text-gray-600">آخرین تست اتصال:</span>
                    {{ $server->last_tested_at ? $server->last_tested_at->diffForHumans() : 'تست نشده' }}
                    @if($server->last_test_status === 'ok')
                        <span class="text-green-600 font-medium"> (موفق)</span>
                    @elseif($server->last_test_status)
                        <span class="text-red-500 font-medium"> (ناموفق)</span>
                    @endif
                </div>
                <div>
                    <span class="font-medium text-gray-600">تعداد نصب‌ها:</span>
                    {{ $server->installations()->count() }}
                </div>
                @if($server->last_php_version)
                <div>
                    <span class="font-medium text-gray-600">PHP:</span> {{ $server->last_php_version }}
                </div>
                @endif
                @if($server->last_disk_free_bytes)
                <div>
                    <span class="font-medium text-gray-600">دیسک آزاد:</span>
                    {{ round($server->last_disk_free_bytes / 1024 / 1024 / 1024, 2) }} GB
                </div>
                @endif
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>

    {{-- Test connection from edit page --}}
    <div class="mt-4 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">تست اتصال</h2>
                <p class="text-xs text-gray-500">بررسی اتصال SSH، نسخه PHP، دیسک و مجوزها</p>
            </div>
            <button onclick="testServer({{ $server->id }})" class="btn btn-outline-secondary text-sm flex items-center gap-1" id="edit-test-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                تست اتصال
            </button>
        </div>
        <div id="edit-test-result" class="hidden text-sm rounded-lg p-3"></div>
    </div>
</div>

@push('scripts')
<script>
function serverEditForm() {
    return {
        authMethod: '{{ old('auth_method', $server->auth_method) }}',
    };
}

function testServer(serverId) {
    const btn = document.getElementById('edit-test-btn');
    const result = document.getElementById('edit-test-result');
    const originalHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> در حال تست...';
    result.classList.add('hidden');

    fetch('/admin/infrastructure/customer-deployment/servers/' + serverId + '/test', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        result.classList.remove('hidden');

        if (data.success) {
            result.className = 'text-sm rounded-lg p-3 bg-green-50 border border-green-200 text-green-800';
            result.innerHTML =
                '<p class="font-semibold mb-1">✓ اتصال موفق</p>' +
                '<div class="grid grid-cols-2 gap-1 text-xs">' +
                (data.php_version ? '<div><span class="font-medium">PHP:</span> ' + data.php_version + '</div>' : '') +
                (data.disk_free   ? '<div><span class="font-medium">دیسک آزاد:</span> ' + data.disk_free + '</div>' : '') +
                (data.fingerprint ? '<div class="col-span-2"><span class="font-medium">SSH Fingerprint:</span> <code class="font-mono text-gray-600">' + data.fingerprint + '</code></div>' : '') +
                '</div>';
        } else {
            result.className = 'text-sm rounded-lg p-3 bg-red-50 border border-red-200 text-red-800';
            result.innerHTML = '<p class="font-semibold">✗ اتصال ناموفق</p><p class="text-xs mt-1">' + (data.message || 'خطای ناشناخته') + '</p>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        result.classList.remove('hidden');
        result.className = 'text-sm rounded-lg p-3 bg-red-50 border border-red-200 text-red-800';
        result.innerHTML = '<p class="font-semibold">✗ خطا در ارتباط</p><p class="text-xs mt-1">' + err.message + '</p>';
    });
}
</script>
@endpush

@endsection
