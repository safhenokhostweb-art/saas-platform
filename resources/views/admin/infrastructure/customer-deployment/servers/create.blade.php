@extends('layouts.admin')

@section('header', 'افزودن مقصد استقرار')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-900">افزودن مقصد استقرار</h1>
        <p class="text-sm text-gray-500">نوع مقصد را انتخاب کنید — هر نوع فرم و قابلیت‌های خاص خود را دارد</p>
    </div>
</div>

<div class="max-w-3xl" x-data="deploymentTargetForm()">

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
            <p class="text-red-800 text-sm font-semibold mb-1">خطاهای اعتبارسنجی:</p>
            <ul class="text-red-700 text-sm list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         STEP 1 — Type Selector
    ═══════════════════════════════════════════════ --}}
    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold">۱</span>
            انتخاب نوع مقصد استقرار
        </h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">

            {{-- VPS / SSH --}}
            <button type="button" x-on:click="selectType('ssh')"
                :class="{ 'ring-2 ring-indigo-500 border-indigo-300 bg-indigo-50': selectedType === 'ssh', 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50': selectedType !== 'ssh' }"
                class="relative text-right p-4 rounded-xl border-2 transition-all cursor-pointer group">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 text-sm">VPS / سرور اختصاصی</div>
                        <div class="text-xs text-gray-500 mt-0.5">SSH — دسترسی کامل</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1 mt-3">
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">صف کار</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">Redis</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">SSL</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">+۶</span>
                </div>
                <div x-show="selectedType === 'ssh'" class="absolute top-2 left-2 w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
            </button>

            {{-- DirectAdmin --}}
            <button type="button" x-on:click="selectType('directadmin')"
                :class="{ 'ring-2 ring-blue-500 border-blue-300 bg-blue-50': selectedType === 'directadmin', 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50': selectedType !== 'directadmin' }"
                class="relative text-right p-4 rounded-xl border-2 transition-all cursor-pointer">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 text-sm">DirectAdmin</div>
                        <div class="text-xs text-gray-500 mt-0.5">هاستینگ اشتراکی</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1 mt-3">
                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">API Token</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">SSL</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">DB</span>
                </div>
                <span class="absolute top-2 left-2 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">به زودی</span>
                <div x-show="selectedType === 'directadmin'" class="absolute top-2 left-2 w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
            </button>

            {{-- cPanel --}}
            <button type="button" x-on:click="selectType('cpanel')"
                :class="{ 'ring-2 ring-orange-500 border-orange-300 bg-orange-50': selectedType === 'cpanel', 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50': selectedType !== 'cpanel' }"
                class="relative text-right p-4 rounded-xl border-2 transition-all cursor-pointer">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 text-sm">cPanel</div>
                        <div class="text-xs text-gray-500 mt-0.5">هاستینگ اشتراکی</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1 mt-3">
                    <span class="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">API Token</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">SSL</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">DB</span>
                </div>
                <span class="absolute top-2 left-2 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">به زودی</span>
                <div x-show="selectedType === 'cpanel'" class="absolute top-2 left-2 w-5 h-5 bg-orange-600 rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
            </button>

            {{-- SFTP --}}
            <button type="button" x-on:click="selectType('sftp')"
                :class="{ 'ring-2 ring-teal-500 border-teal-300 bg-teal-50': selectedType === 'sftp', 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50': selectedType !== 'sftp' }"
                class="relative text-right p-4 rounded-xl border-2 transition-all cursor-pointer">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 text-sm">SFTP خالص</div>
                        <div class="text-xs text-gray-500 mt-0.5">فقط انتقال فایل</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1 mt-3">
                    <span class="text-xs bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded">آپلود</span>
                    <span class="text-xs bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded line-through">DB</span>
                    <span class="text-xs bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded line-through">Shell</span>
                </div>
                <span class="absolute top-2 left-2 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">به زودی</span>
                <div x-show="selectedType === 'sftp'" class="absolute top-2 left-2 w-5 h-5 bg-teal-600 rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
            </button>

            {{-- Docker --}}
            <button type="button" x-on:click="selectType('docker')"
                :class="{ 'ring-2 ring-cyan-500 border-cyan-300 bg-cyan-50': selectedType === 'docker', 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50': selectedType !== 'docker' }"
                class="relative text-right p-4 rounded-xl border-2 transition-all cursor-pointer">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 text-sm">Docker Host</div>
                        <div class="text-xs text-gray-500 mt-0.5">کانتینر Docker</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1 mt-3">
                    <span class="text-xs bg-cyan-100 text-cyan-700 px-1.5 py-0.5 rounded">Docker</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">Redis</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">DB</span>
                </div>
                <span class="absolute top-2 left-2 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">به زودی</span>
                <div x-show="selectedType === 'docker'" class="absolute top-2 left-2 w-5 h-5 bg-cyan-600 rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
            </button>

            {{-- Kubernetes (disabled) --}}
            <div class="relative text-right p-4 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 opacity-50 cursor-not-allowed">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-400 text-sm">Kubernetes</div>
                        <div class="text-xs text-gray-400 mt-0.5">در نقشه راه آینده</div>
                    </div>
                </div>
                <span class="absolute top-2 left-2 text-xs text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded">غیرفعال</span>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         STEP 2 — Dynamic Form (shown after type selected)
    ═══════════════════════════════════════════════ --}}
    <div x-show="selectedType !== ''" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Stub warning for non-SSH types --}}
        <div x-show="selectedType !== 'ssh'" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-5 flex gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <p class="text-amber-800 text-sm font-semibold">این نوع مقصد در معماری پیاده‌سازی شده — درایور فعال نیست</p>
                <p class="text-amber-700 text-xs mt-1">می‌توانید مقصد را ذخیره کنید، اما تست اتصال و نصب خودکار تا پیاده‌سازی درایور ممکن نخواهد بود.</p>
            </div>
        </div>

        <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold">۲</span>
            اطلاعات اتصال
        </h2>

        <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.store') }}">
            @csrf
            <input type="hidden" name="type" x-bind:value="selectedType">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">

                {{-- Name --}}
                <div>
                    <label class="form-label">نام مقصد <span class="text-red-500">*</span></label>
                    <input type="text" name="label" value="{{ old('label') }}"
                           placeholder="مثال: VPS آلمان ۱ — سرور اصلی"
                           class="form-control @error('label') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1">نامی که در لیست مقصدهای استقرار نمایش داده می‌شود</p>
                    @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Host --}}
                <div>
                    <label class="form-label">
                        <span x-text="hostLabel">آدرس هاست / IP</span>
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="host" value="{{ old('host') }}"
                           x-bind:placeholder="hostPlaceholder"
                           class="form-control font-mono @error('host') border-red-400 @enderror">
                    @error('host')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Port + Username (SSH/SFTP/Docker) --}}
                <div x-show="needsPort" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">پورت <span class="text-red-500">*</span></label>
                        <input type="number" name="port" x-bind:value="defaultPort" min="1" max="65535"
                               class="form-control font-mono @error('port') border-red-400 @enderror">
                        @error('port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">نام کاربری <span class="text-red-500">*</span></label>
                        <input type="text" name="username" value="{{ old('username', 'root') }}"
                               placeholder="root"
                               class="form-control font-mono @error('username') border-red-400 @enderror">
                        @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Username only (cPanel/DirectAdmin) --}}
                <div x-show="!needsPort && selectedType !== 'sftp'">
                    <label class="form-label">نام کاربری پنل <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="{{ old('username') }}"
                           placeholder="نام کاربری حساب هاستینگ"
                           class="form-control @error('username') border-red-400 @enderror">
                    @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- ─── SSH Authentication (SSH / Docker) ─── --}}
                <div x-show="needsSshAuth">
                    <label class="form-label">روش احراز هویت SSH <span class="text-red-500">*</span></label>
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

                    {{-- Private Key --}}
                    <div x-show="authMethod === 'key'" x-cloak>
                        <label class="form-label">کلید خصوصی SSH <span class="text-red-500">*</span></label>
                        <textarea name="private_key" rows="7"
                            placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                            class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror">{{ old('private_key') }}</textarea>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            با رمزگذاری AES-256-GCM ذخیره می‌شود
                        </p>
                        @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- SSH Password --}}
                    <div x-show="authMethod === 'password'" x-cloak>
                        <label class="form-label">رمز عبور SSH <span class="text-red-500">*</span></label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') border-red-400 @enderror">
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            با رمزگذاری AES-256-GCM ذخیره می‌شود
                        </p>
                        @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- ─── API Token (cPanel / DirectAdmin) ─── --}}
                <div x-show="needsApiToken">
                    <input type="hidden" name="auth_method" value="api_token">
                    <label class="form-label" x-text="apiTokenLabel">API Token <span class="text-red-500">*</span></label>
                    <input type="password" name="api_token" autocomplete="new-password"
                           x-bind:placeholder="apiTokenPlaceholder"
                           class="form-control @error('api_token') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        با رمزگذاری AES-256-GCM ذخیره می‌شود
                    </p>
                    @error('api_token')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- ─── SFTP Password ─── --}}
                <div x-show="selectedType === 'sftp'">
                    <input type="hidden" name="auth_method" value="sftp_password">
                    <label class="form-label">رمز عبور SFTP <span class="text-red-500">*</span></label>
                    <input type="password" name="password" autocomplete="new-password"
                           class="form-control @error('password') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        با رمزگذاری AES-256-GCM ذخیره می‌شود
                    </p>
                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Domain (panel types and SFTP) --}}
                <div x-show="needsDomain">
                    <label class="form-label">دامنه اصلی</label>
                    <input type="text" name="domain" value="{{ old('domain') }}"
                           placeholder="example.com"
                           class="form-control font-mono @error('domain') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1">دامنه‌ای که روی این مقصد سرویس می‌دهد</p>
                    @error('domain')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Path (panel types and SFTP) --}}
                <div x-show="needsPath">
                    <label class="form-label">مسیر وب روت</label>
                    <input type="text" name="path" value="{{ old('path') }}"
                           placeholder="/home/user/public_html"
                           class="form-control font-mono @error('path') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1">مسیر پوشه عمومی هاستینگ (public_html یا www)</p>
                    @error('path')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

            </div>

            {{-- ═══════════════════════════════════════════════
                 Capability Preview Panel
            ═══════════════════════════════════════════════ --}}
            <div class="mt-4 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-xs font-bold">۳</span>
                    قابلیت‌های این مقصد
                </h3>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2" x-ref="capGrid">
                    <template x-for="[cap, label] in capLabels" :key="cap">
                        <div :class="capabilities[cap] ? 'bg-green-50 border-green-200 text-green-700' : 'bg-gray-50 border-gray-200 text-gray-400'"
                             class="border rounded-lg px-2.5 py-2 text-center">
                            <div :class="capabilities[cap] ? 'text-green-500' : 'text-gray-300'" class="text-lg mb-0.5">
                                <span x-text="capabilities[cap] ? '✓' : '✗'"></span>
                            </div>
                            <div class="text-xs font-medium" x-text="label"></div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Security notice --}}
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 flex gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>تمام اطلاعات اتصال با رمزگذاری AES-256-GCM ذخیره می‌شوند. دسترسی فقط برای عملیات نصب مشتریان مورد استفاده قرار می‌گیرد.</span>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="submit" class="btn btn-primary">ذخیره مقصد استقرار</button>
                <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>

    {{-- No type selected hint --}}
    <div x-show="selectedType === ''" class="text-center py-8 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>
        </svg>
        <p class="text-sm">یک نوع مقصد استقرار را از بالا انتخاب کنید</p>
    </div>

</div>

@push('scripts')
<script>
function deploymentTargetForm() {
    const capabilityMap = {
        ssh:         { queue: true,  supervisor: true,  redis: true,  ssl: true,  database: true,  storage: true,  cron: true,  docker: true,  reboot: true  },
        directadmin: { queue: false, supervisor: false, redis: false, ssl: true,  database: true,  storage: false, cron: true,  docker: false, reboot: false },
        cpanel:      { queue: false, supervisor: false, redis: false, ssl: true,  database: true,  storage: false, cron: true,  docker: false, reboot: false },
        sftp:        { queue: false, supervisor: false, redis: false, ssl: false, database: false, storage: false, cron: false, docker: false, reboot: false },
        docker:      { queue: true,  supervisor: false, redis: true,  ssl: false, database: true,  storage: true,  cron: true,  docker: true,  reboot: true  },
    };

    const defaultPorts = { ssh: 22, directadmin: 2222, cpanel: 2083, sftp: 22, docker: 22 };

    return {
        selectedType: '{{ old('type', '') }}',
        authMethod: '{{ old('auth_method', 'key') }}',
        capabilities: {},

        capLabels: Object.entries({
            queue: 'صف کار', supervisor: 'Supervisor', redis: 'Redis',
            ssl: 'SSL', database: 'ساخت DB', storage: 'Storage Link',
            cron: 'زمانبند', docker: 'Docker', reboot: 'ریبوت',
        }),

        get needsPort()    { return ['ssh', 'sftp', 'docker'].includes(this.selectedType); },
        get needsSshAuth() { return ['ssh', 'docker'].includes(this.selectedType); },
        get needsApiToken(){ return ['cpanel', 'directadmin'].includes(this.selectedType); },
        get needsDomain()  { return ['cpanel', 'directadmin', 'sftp'].includes(this.selectedType); },
        get needsPath()    { return ['cpanel', 'directadmin', 'sftp'].includes(this.selectedType); },
        get defaultPort()  { return defaultPorts[this.selectedType] || 22; },

        get hostLabel() {
            const labels = { ssh: 'آدرس IP / Hostname', directadmin: 'هاست پنل DirectAdmin', cpanel: 'هاست پنل cPanel', sftp: 'هاست SFTP', docker: 'هاست سرور Docker' };
            return labels[this.selectedType] || 'آدرس هاست / IP';
        },
        get hostPlaceholder() {
            const placeholders = { ssh: '185.x.x.x یا server.example.com', directadmin: 'panel.example.com', cpanel: 'cpanel.example.com', sftp: 'ftp.example.com', docker: 'docker.example.com' };
            return placeholders[this.selectedType] || 'example.com';
        },
        get apiTokenLabel() {
            if (this.selectedType === 'cpanel') return 'cPanel API Token *';
            if (this.selectedType === 'directadmin') return 'DirectAdmin API Key *';
            return 'API Token *';
        },
        get apiTokenPlaceholder() {
            if (this.selectedType === 'cpanel') return 'cPanel API Token از Security → Manage API Tokens';
            return 'کلید API DirectAdmin';
        },

        selectType(type) {
            this.selectedType = type;
            this.capabilities = capabilityMap[type] || {};
            // Reset auth method default per driver
            if (type === 'ssh' || type === 'docker') {
                this.authMethod = 'key';
            }
        },

        init() {
            if (this.selectedType) {
                this.capabilities = capabilityMap[this.selectedType] || {};
            }
        }
    };
}
</script>
@endpush

@endsection
