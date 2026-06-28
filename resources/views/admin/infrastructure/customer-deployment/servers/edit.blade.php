@extends('layouts.admin')

@section('header', 'ویرایش مقصد استقرار')

@section('content')

@php
    $meta = $driverMeta[$server->type] ?? $driverMeta['ssh'];
    $typeColorMap = [
        'ssh'         => ['ring' => 'ring-indigo-200',  'bg' => 'bg-indigo-50',  'text' => 'text-indigo-700',  'dot' => 'bg-indigo-500'],
        'directadmin' => ['ring' => 'ring-blue-200',    'bg' => 'bg-blue-50',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500'],
        'cpanel'      => ['ring' => 'ring-orange-200',  'bg' => 'bg-orange-50',  'text' => 'text-orange-700',  'dot' => 'bg-orange-500'],
        'sftp'        => ['ring' => 'ring-teal-200',    'bg' => 'bg-teal-50',    'text' => 'text-teal-700',    'dot' => 'bg-teal-500'],
        'docker'      => ['ring' => 'ring-cyan-200',    'bg' => 'bg-cyan-50',    'text' => 'text-cyan-700',    'dot' => 'bg-cyan-500'],
    ];
    $tc = $typeColorMap[$server->type] ?? $typeColorMap['ssh'];
    $capLabels = ['queue'=>'صف کار','supervisor'=>'Supervisor','redis'=>'Redis','ssl'=>'SSL','database'=>'ساخت DB','storage'=>'Storage Link','cron'=>'زمانبند','docker'=>'Docker','reboot'=>'ریبوت'];
@endphp

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">ویرایش: {{ $server->label }}</h1>
        <p class="text-sm text-gray-500 font-mono">{{ $server->username }}@{{ $server->host }}:{{ $server->port }}</p>
    </div>
</div>

<div class="max-w-3xl">

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
            <p class="text-red-800 text-sm font-semibold mb-1">خطاهای اعتبارسنجی:</p>
            <ul class="text-red-700 text-sm list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Type badge (locked) --}}
    <div class="flex items-center gap-3 mb-5 p-4 bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 {{ $tc['bg'] }} {{ $tc['ring'] }} ring-1 px-3 py-1.5 rounded-lg">
            <span class="w-2 h-2 rounded-full {{ $tc['dot'] }}"></span>
            <span class="text-sm font-semibold {{ $tc['text'] }}">{{ $meta['label'] }}</span>
        </div>
        <div class="text-sm text-gray-500 flex-1">نوع مقصد قفل شده — برای نوع دیگر، مقصد جدید ایجاد کنید</div>
        @if(!$meta['implemented'])
            <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">درایور فعال نیست</span>
        @endif
    </div>

    @if(!$meta['implemented'])
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-5 flex gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="text-amber-800 text-sm font-semibold">درایور {{ $meta['label'] }} هنوز پیاده‌سازی نشده</p>
            <p class="text-amber-700 text-xs mt-1">اطلاعات ذخیره می‌شود اما تست اتصال و نصب خودکار تا پیاده‌سازی درایور ممکن نخواهد بود.</p>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.update', $server) }}">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">

            {{-- Name --}}
            <div>
                <label class="form-label">نام مقصد <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label', $server->label) }}"
                       class="form-control @error('label') border-red-400 @enderror">
                @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Host --}}
            <div>
                <label class="form-label">آدرس هاست / IP <span class="text-red-500">*</span></label>
                <input type="text" name="host" value="{{ old('host', $server->host) }}"
                       class="form-control font-mono @error('host') border-red-400 @enderror">
                @error('host')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Port + Username (SSH / SFTP / Docker) --}}
            @if(in_array($server->type, ['ssh', 'sftp', 'docker']))
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">پورت <span class="text-red-500">*</span></label>
                    <input type="number" name="port" value="{{ old('port', $server->port) }}"
                           min="1" max="65535"
                           class="form-control font-mono @error('port') border-red-400 @enderror">
                    @error('port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">نام کاربری <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="{{ old('username', $server->username) }}"
                           class="form-control font-mono @error('username') border-red-400 @enderror">
                    @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            @endif

            {{-- Username + Port (cPanel / DirectAdmin) --}}
            @if(in_array($server->type, ['cpanel', 'directadmin']))
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">نام کاربری پنل <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="{{ old('username', $server->username) }}"
                           class="form-control @error('username') border-red-400 @enderror">
                    @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">پورت پنل</label>
                    <input type="number" name="port" value="{{ old('port', $server->port) }}"
                           min="1" max="65535"
                           class="form-control font-mono @error('port') border-red-400 @enderror">
                    @error('port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            @endif

            {{-- ─── SSH Auth (SSH / Docker) ─── --}}
            @if(in_array($server->type, ['ssh', 'docker']))
            <div x-data="{ authMethod: '{{ old('auth_method', $server->auth_method ?? 'key') }}' }">
                <label class="form-label">روش احراز هویت SSH</label>
                <div class="flex gap-4 mt-1 mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="auth_method" value="key" x-model="authMethod" class="text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">کلید خصوصی SSH</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="auth_method" value="password" x-model="authMethod" class="text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">رمز عبور SSH</span>
                    </label>
                </div>

                <div x-show="authMethod === 'key'" x-cloak>
                    <label class="form-label">
                        کلید خصوصی SSH جدید
                        <span class="text-gray-400 font-normal text-xs mr-1">(خالی = بدون تغییر)</span>
                    </label>
                    <textarea name="private_key" rows="6"
                        placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;برای بازنویسی کلید موجود، کلید جدید را وارد کنید&#10;-----END RSA PRIVATE KEY-----"
                        class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror">{{ old('private_key') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        کلید موجود با رمزگذاری ذخیره است — خالی گذاشتن آن را حفظ می‌کند
                    </p>
                    @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div x-show="authMethod === 'password'" x-cloak>
                    <label class="form-label">
                        رمز عبور SSH جدید
                        <span class="text-gray-400 font-normal text-xs mr-1">(خالی = بدون تغییر)</span>
                    </label>
                    <input type="password" name="password" autocomplete="new-password"
                           placeholder="برای تغییر رمز، رمز جدید وارد کنید"
                           class="form-control @error('password') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        رمز موجود با رمزگذاری ذخیره است — خالی گذاشتن آن را حفظ می‌کند
                    </p>
                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            @endif

            {{-- ─── API Token (cPanel / DirectAdmin) ─── --}}
            @if(in_array($server->type, ['cpanel', 'directadmin']))
            <div>
                <label class="form-label">
                    {{ $server->type === 'cpanel' ? 'cPanel API Token جدید' : 'DirectAdmin API Key جدید' }}
                    <span class="text-gray-400 font-normal text-xs mr-1">(خالی = بدون تغییر)</span>
                </label>
                <input type="password" name="api_token" autocomplete="new-password"
                       placeholder="برای بازنویسی توکن موجود، توکن جدید وارد کنید"
                       class="form-control @error('api_token') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    توکن موجود با رمزگذاری ذخیره است — خالی گذاشتن آن را حفظ می‌کند
                </p>
                @error('api_token')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

            {{-- ─── SFTP Password ─── --}}
            @if($server->type === 'sftp')
            <div>
                <label class="form-label">
                    رمز عبور SFTP جدید
                    <span class="text-gray-400 font-normal text-xs mr-1">(خالی = بدون تغییر)</span>
                </label>
                <input type="password" name="password" autocomplete="new-password"
                       placeholder="برای تغییر رمز، رمز جدید وارد کنید"
                       class="form-control @error('password') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    رمز موجود با رمزگذاری ذخیره است — خالی گذاشتن آن را حفظ می‌کند
                </p>
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

            {{-- Domain --}}
            @if(in_array($server->type, ['cpanel', 'directadmin', 'sftp']))
            <div>
                <label class="form-label">دامنه اصلی</label>
                <input type="text" name="domain" value="{{ old('domain', $server->domain) }}"
                       placeholder="example.com"
                       class="form-control font-mono @error('domain') border-red-400 @enderror">
                @error('domain')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

            {{-- Path --}}
            @if(in_array($server->type, ['cpanel', 'directadmin', 'sftp']))
            <div>
                <label class="form-label">مسیر وب روت</label>
                <input type="text" name="path" value="{{ old('path', $server->path) }}"
                       placeholder="/home/user/public_html"
                       class="form-control font-mono @error('path') border-red-400 @enderror">
                @error('path')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

        </div>

        {{-- Capability snapshot --}}
        <div class="mt-4 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">قابلیت‌های این مقصد</h3>
            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                @foreach($capLabels as $cap => $label)
                    @php $supported = $meta['capabilities'][$cap] ?? false; @endphp
                    <div class="{{ $supported ? 'bg-green-50 border-green-200 text-green-700' : 'bg-gray-50 border-gray-200 text-gray-400' }} border rounded-lg px-2.5 py-2 text-center">
                        <div class="{{ $supported ? 'text-green-500' : 'text-gray-300' }} text-lg mb-0.5">{{ $supported ? '✓' : '✗' }}</div>
                        <div class="text-xs font-medium">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Server metadata --}}
        <div class="mt-4 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">اطلاعات تست اتصال</h3>
            <div class="grid grid-cols-2 gap-3 text-xs text-gray-600">
                <div>
                    <span class="font-medium text-gray-500">آخرین تست:</span>
                    {{ $server->last_tested_at ? $server->last_tested_at->diffForHumans() : 'تست نشده' }}
                    @if($server->last_test_result === 'success')
                        <span class="text-green-600 font-medium"> (موفق)</span>
                    @elseif($server->last_test_result)
                        <span class="text-red-500 font-medium"> (ناموفق)</span>
                    @endif
                </div>
                <div>
                    <span class="font-medium text-gray-500">تعداد نصب‌ها:</span>
                    {{ $server->installations()->count() }}
                </div>
            </div>

            @if($meta['implemented'])
            <div class="mt-4 flex items-center gap-3">
                <button type="button" onclick="testTarget({{ $server->id }}, document.getElementById('edit-test-btn'))"
                        id="edit-test-btn"
                        class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    تست اتصال
                </button>
                <div id="edit-test-result" class="text-xs text-gray-500"></div>
            </div>
            @else
            <p class="text-xs text-gray-400 mt-3">تست اتصال برای این نوع درایور هنوز فعال نشده است.</p>
            @endif
        </div>

        <div class="mt-5 flex gap-3">
            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
            <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>

</div>

@push('scripts')
<script>
function testTarget(id, btn) {
    const resultEl  = document.getElementById('edit-test-result');
    const origHtml  = btn.innerHTML;

    btn.disabled  = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> در حال تست...';
    resultEl.textContent = '';

    fetch('/admin/infrastructure/customer-deployment/servers/' + id + '/test', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled  = false;
        btn.innerHTML = origHtml;

        if (data.success) {
            resultEl.innerHTML =
                '<span class="text-green-600 font-semibold">✓ اتصال موفق</span>' +
                (data.os          ? ' · ' + data.os : '') +
                (data.php_version ? ' · PHP ' + data.php_version : '') +
                (data.disk_free   ? ' · دیسک آزاد: ' + data.disk_free : '');
        } else {
            resultEl.innerHTML = '<span class="text-red-500 font-semibold">✗ ناموفق: </span>' + (data.message || 'خطای ناشناخته');
        }
    })
    .catch(err => {
        btn.disabled  = false;
        btn.innerHTML = origHtml;
        resultEl.innerHTML = '<span class="text-red-500">✗ خطا: ' + err.message + '</span>';
    });
}
</script>
@endpush

@endsection
