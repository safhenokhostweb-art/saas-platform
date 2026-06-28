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
        <p class="text-sm text-gray-500">نوع مقصد را انتخاب کنید — احراز هویت بر اساس درایور تنظیم می‌شود</p>
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
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">کلید SSH</span>
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">رمز عبور</span>
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">+۹ قابلیت</span>
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
                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">API Key</span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">رمز عبور</span>
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
                    <span class="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">رمز عبور</span>
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
                    <span class="text-xs bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded">نام کاربری + رمز</span>
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
                    <span class="text-xs bg-cyan-100 text-cyan-700 px-1.5 py-0.5 rounded">کلید SSH</span>
                    <span class="text-xs bg-cyan-100 text-cyan-700 px-1.5 py-0.5 rounded">رمز عبور</span>
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
         STEP 2 — Connection Info + Auth (shown after type selected)
    ═══════════════════════════════════════════════ --}}
    <div x-show="selectedType !== ''" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Stub warning for non-SSH types --}}
        <div x-show="selectedType !== 'ssh'" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-5 flex gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <p class="text-amber-800 text-sm font-semibold">این درایور در معماری تعریف شده — اجرا فعال نیست</p>
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

            {{-- Hidden field: actual auth_method sent to server --}}
            <input type="hidden" name="auth_method" x-bind:value="resolvedAuthMethod">

            {{-- Hidden field: private key (managed by Alpine) --}}
            <input type="hidden" name="private_key" x-bind:value="resolvedPrivateKey">

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

                {{-- Port + Username (SSH / SFTP / Docker) --}}
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

                {{-- Username only (cPanel / DirectAdmin) --}}
                <div x-show="!needsPort" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">نام کاربری پنل <span class="text-red-500">*</span></label>
                        <input type="text" name="username" value="{{ old('username') }}"
                               placeholder="نام کاربری حساب هاستینگ"
                               class="form-control @error('username') border-red-400 @enderror">
                        @error('username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">پورت پنل</label>
                        <input type="number" name="port" x-bind:value="defaultPort" min="1" max="65535"
                               class="form-control font-mono @error('port') border-red-400 @enderror">
                        @error('port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- ════════════════════════════════════════════════════
                     PART 1+2 — SSH / Docker: Authentication Method Selector
                ════════════════════════════════════════════════════ --}}
                <div x-show="needsSshAuth">

                    <label class="form-label mb-3 block">روش احراز هویت SSH <span class="text-red-500">*</span></label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                        {{-- Existing Key --}}
                        <button type="button" x-on:click="sshMode = 'existing_key'"
                            :class="sshMode === 'existing_key' ? 'ring-2 ring-indigo-500 border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-3 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900">کلید SSH موجود</span>
                            </div>
                            <p class="text-xs text-gray-500">کلید خصوصی از فایل</p>
                            <div x-show="sshMode === 'existing_key'" class="absolute top-2 left-2 w-4 h-4 bg-indigo-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>

                        {{-- Generate New Key --}}
                        <button type="button" x-on:click="sshMode = 'generate_key'"
                            :class="sshMode === 'generate_key' ? 'ring-2 ring-green-500 border-green-300 bg-green-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-3 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900">تولید کلید جدید</span>
                            </div>
                            <p class="text-xs text-gray-500">Ed25519 — پیشنهادی</p>
                            <span class="absolute top-2 left-2 text-xs bg-green-100 text-green-700 px-1 py-0.5 rounded">پیشنهادی</span>
                            <div x-show="sshMode === 'generate_key'" class="absolute top-2 left-2 w-4 h-4 bg-green-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>

                        {{-- Username + Password --}}
                        <button type="button" x-on:click="sshMode = 'password'"
                            :class="sshMode === 'password' ? 'ring-2 ring-gray-500 border-gray-400 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-3 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900">رمز عبور SSH</span>
                            </div>
                            <p class="text-xs text-gray-500">روش ساده‌تر — کمتر امن</p>
                            <div x-show="sshMode === 'password'" class="absolute top-2 left-2 w-4 h-4 bg-gray-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>
                    </div>

                    {{-- ─── Existing SSH Key ─── --}}
                    <div x-show="sshMode === 'existing_key'" x-cloak>
                        <label class="form-label">کلید خصوصی SSH <span class="text-red-500">*</span></label>
                        <textarea x-ref="existingKeyTextarea" rows="7"
                            @input="rawPrivateKey = $event.target.value"
                            placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"
                            class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror">{{ old('private_key') }}</textarea>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            با رمزگذاری AES-256-GCM ذخیره می‌شود — هرگز به‌صورت متن ساده نمایش داده نمی‌شود
                        </p>
                        @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- ─── Generate New SSH Key Wizard ─── --}}
                    <div x-show="sshMode === 'generate_key'" x-cloak>

                        {{-- Step: Not yet generated --}}
                        <div x-show="!keyGenerated" class="bg-green-50 border border-green-200 rounded-xl p-5">
                            <div class="flex items-start gap-3 mb-4">
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-green-900">تولید کلید Ed25519 امن</p>
                                    <p class="text-xs text-green-700 mt-0.5">کلید خصوصی هرگز از مرورگر شما خارج نمی‌شود — مستقیم رمزگذاری و ذخیره می‌شود</p>
                                </div>
                            </div>
                            <button type="button" @click="generateKey()" :disabled="keyGenerating"
                                class="w-full py-3 px-4 rounded-lg bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-semibold text-sm flex items-center justify-center gap-2 transition-colors">
                                <svg x-show="!keyGenerating" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                <svg x-show="keyGenerating" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span x-text="keyGenerating ? 'در حال تولید...' : 'ایجاد کلید Ed25519'"></span>
                            </button>
                            <div x-show="keyGenError" class="mt-2 text-xs text-red-600" x-text="keyGenError"></div>
                        </div>

                        {{-- Step: Key generated — show public key for copying --}}
                        <div x-show="keyGenerated" x-cloak class="space-y-4">

                            {{-- Success banner --}}
                            <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-green-900">کلید Ed25519 ایجاد شد</p>
                                    <p class="text-xs text-green-700">کلید خصوصی در حافظه نگه داشته شده — با ذخیره فرم رمزگذاری می‌شود</p>
                                </div>
                            </div>

                            {{-- STEP 2: Public Key Display --}}
                            <div class="bg-white border border-gray-200 rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        کلید عمومی (Public Key) — ایمن برای کپی روی سرور
                                    </p>
                                    {{-- STEP 3: Copy Button --}}
                                    <button type="button" @click="copyPublicKey()"
                                        class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 font-medium flex items-center gap-1 transition-colors">
                                        <svg x-show="!keyCopied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        <svg x-show="keyCopied" class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        <span x-text="keyCopied ? 'کپی شد!' : 'کپی کردن'"></span>
                                    </button>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-3 font-mono text-xs text-green-400 break-all select-all" x-text="generatedPublicKey"></div>
                            </div>

                            {{-- STEP 4: Guide where to paste --}}
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                                <p class="text-sm font-semibold text-blue-900 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    راهنما: نصب کلید عمومی روی سرور
                                </p>
                                <div class="space-y-2 text-xs text-blue-800">
                                    <div class="flex gap-2">
                                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shrink-0">۱</span>
                                        <span>به سرور SSH کنید: <code class="bg-blue-100 px-1 rounded">ssh root@&lt;ip-سرور&gt;</code></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shrink-0">۲</span>
                                        <span>فایل را باز کنید: <code class="bg-blue-100 px-1 rounded">nano ~/.ssh/authorized_keys</code></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shrink-0">۳</span>
                                        <span>کلید عمومی بالا را در یک خط جدید در انتها اضافه کنید</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shrink-0">۴</span>
                                        <span>ذخیره کنید: <code class="bg-blue-100 px-1 rounded">Ctrl+O</code> سپس <code class="bg-blue-100 px-1 rounded">Enter</code> سپس <code class="bg-blue-100 px-1 rounded">Ctrl+X</code></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">یا</span>
                                        <span>دستور سریع: <code class="bg-blue-100 px-1 rounded">echo 'PUBLIC_KEY' &gt;&gt; ~/.ssh/authorized_keys</code></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Confirmation checkbox + Key installed indicator --}}
                            <div class="flex items-start gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <input type="checkbox" x-model="keyInstalled" id="key_installed_confirm"
                                       class="mt-0.5 w-4 h-4 text-green-600 rounded border-gray-300">
                                <label for="key_installed_confirm" class="text-sm text-gray-700 cursor-pointer">
                                    کلید عمومی را روی سرور در فایل <code class="bg-gray-100 px-1 rounded text-xs">authorized_keys</code> نصب کردم
                                </label>
                            </div>

                            {{-- STEP 5+6: Test + Activate note --}}
                            <div class="text-xs text-gray-500 bg-gray-50 border border-gray-100 rounded-lg p-3">
                                <svg class="w-3.5 h-3.5 inline text-gray-400 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                پس از ذخیره، از صفحه ویرایش مقصد می‌توانید اتصال را تست کنید (مرحله ۵) و مقصد را فعال کنید (مرحله ۶).
                            </div>

                            <button type="button" @click="keyGenerated = false; generatedPublicKey = ''; generatedPrivateKey = ''; keyInstalled = false;"
                                class="text-xs text-gray-400 hover:text-gray-600 underline">
                                بازتولید کلید
                            </button>
                        </div>

                    </div>

                    {{-- ─── SSH Password ─── --}}
                    <div x-show="sshMode === 'password'" x-cloak>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3 flex gap-2">
                            <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-xs text-amber-800">رمز عبور کمتر از کلید SSH امن است. برای محیط‌های تولید، استفاده از کلید SSH توصیه می‌شود.</p>
                        </div>
                        <label class="form-label">رمز عبور SSH <span class="text-red-500">*</span></label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') border-red-400 @enderror">
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            با رمزگذاری AES-256-GCM ذخیره می‌شود
                        </p>
                        @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- ════════════════════════════════════════════
                         PART 4 — SSH Education Panel (expandable)
                    ════════════════════════════════════════════ --}}
                    <div class="mt-4" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                            class="w-full flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-100 transition-colors">
                            <span class="flex items-center gap-2 font-medium">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                کلید SSH چیست؟ — راهنمای تصویری
                            </span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-transition class="mt-2 bg-slate-50 border border-slate-200 rounded-xl p-5">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-5">
                                {{-- Private Key --}}
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                        </div>
                                        <span class="font-semibold text-gray-800 text-sm">کلید خصوصی</span>
                                    </div>
                                    <ul class="text-xs text-gray-600 space-y-1.5">
                                        <li class="flex items-start gap-1.5"><span class="text-red-500 font-bold mt-0.5">✗</span> هرگز به‌جای دیگری ارسال نمی‌شود</li>
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> فقط در این پلتفرم نگه داشته می‌شود</li>
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> با AES-256-GCM رمزگذاری می‌شود</li>
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> برای امضای اتصال استفاده می‌شود</li>
                                    </ul>
                                </div>

                                {{-- Public Key --}}
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                        </div>
                                        <span class="font-semibold text-gray-800 text-sm">کلید عمومی</span>
                                    </div>
                                    <ul class="text-xs text-gray-600 space-y-1.5">
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> فقط یک بار روی سرور کپی می‌شود</li>
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> نشان دادن آن کاملاً ایمن است</li>
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> در ~/.ssh/authorized_keys سرور ذخیره می‌شود</li>
                                        <li class="flex items-start gap-1.5"><span class="text-green-500 font-bold mt-0.5">✓</span> برای تأیید امضا استفاده می‌شود</li>
                                    </ul>
                                </div>
                            </div>

                            {{-- Connection diagram --}}
                            <div class="bg-gray-900 rounded-xl p-4 font-mono text-xs text-center">
                                <div class="grid grid-cols-3 gap-2 items-center text-gray-300">
                                    <div class="bg-slate-800 rounded-lg p-2">
                                        <div class="text-indigo-400 font-bold mb-1">این پلتفرم</div>
                                        <div class="text-gray-400">کلید خصوصی</div>
                                        <div class="text-gray-500 text-xs">(رمزگذاری شده)</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-green-400 text-xs mb-1">امضا می‌کند</div>
                                        <div class="text-2xl">→</div>
                                        <div class="text-green-400 text-xs mt-1">تأیید می‌کند</div>
                                    </div>
                                    <div class="bg-slate-800 rounded-lg p-2">
                                        <div class="text-cyan-400 font-bold mb-1">سرور شما</div>
                                        <div class="text-gray-400">کلید عمومی</div>
                                        <div class="text-gray-500 text-xs">(authorized_keys)</div>
                                    </div>
                                </div>
                                <div class="mt-3 text-green-400 text-xs">✓ بدون رمز عبور — اتصال امن</div>
                            </div>

                        </div>
                    </div>

                </div>

                {{-- ════════════════════════════════════════════════════
                     PART 6 — cPanel / DirectAdmin: Authentication Selector
                ════════════════════════════════════════════════════ --}}
                <div x-show="needsPanelAuth">

                    <label class="form-label mb-3 block">روش احراز هویت <span class="text-red-500">*</span></label>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        {{-- API Token (Recommended) --}}
                        <button type="button" x-on:click="panelAuthMode = 'api_token'"
                            :class="panelAuthMode === 'api_token' ? 'ring-2 ring-orange-500 border-orange-300 bg-orange-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-4 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900" x-text="selectedType === 'cpanel' ? 'API Token' : 'API Key'"></span>
                            </div>
                            <p class="text-xs text-gray-500">امن‌تر — توکن اختصاصی</p>
                            <span class="absolute top-2 left-2 text-xs bg-orange-100 text-orange-700 px-1 py-0.5 rounded">پیشنهادی</span>
                            <div x-show="panelAuthMode === 'api_token'" class="absolute top-2 left-2 w-4 h-4 bg-orange-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>

                        {{-- Username + Password --}}
                        <button type="button" x-on:click="panelAuthMode = 'password'"
                            :class="panelAuthMode === 'password' ? 'ring-2 ring-gray-500 border-gray-400 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-4 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900">نام کاربری + رمز عبور</span>
                            </div>
                            <p class="text-xs text-gray-500">احراز هویت legacy</p>
                            <div x-show="panelAuthMode === 'password'" class="absolute top-2 left-2 w-4 h-4 bg-gray-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>
                    </div>

                    {{-- API Token Field --}}
                    <div x-show="panelAuthMode === 'api_token'" x-cloak>
                        <label class="form-label">
                            <span x-text="selectedType === 'cpanel' ? 'cPanel API Token' : 'DirectAdmin API Key'"></span>
                            <span class="text-red-500"> *</span>
                        </label>
                        <input type="password" name="api_token" autocomplete="new-password"
                               :placeholder="selectedType === 'cpanel' ? 'از Security › Manage API Tokens در cPanel' : 'API Key از پنل DirectAdmin'"
                               class="form-control @error('api_token') border-red-400 @enderror">
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            با رمزگذاری AES-256-GCM ذخیره می‌شود — هرگز به‌صورت متن ساده نمایش داده نمی‌شود
                        </p>
                        @error('api_token')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    {{-- Password Field --}}
                    <div x-show="panelAuthMode === 'password'" x-cloak>
                        <label class="form-label">رمز عبور پنل <span class="text-red-500">*</span></label>
                        <input type="password" name="password" autocomplete="new-password"
                               placeholder="رمز عبور حساب هاستینگ"
                               class="form-control @error('password') border-red-400 @enderror">
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            با رمزگذاری AES-256-GCM ذخیره می‌شود
                        </p>
                        @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                </div>

                {{-- ─── SFTP Password (only auth method for SFTP) ─── --}}
                <div x-show="selectedType === 'sftp'">
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
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
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
            <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-3 text-xs text-green-800 flex gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>تمام اعتبارنامه‌ها با رمزگذاری AES-256-GCM ذخیره می‌شوند. رمزهای عبور و توکن‌ها پس از ذخیره هرگز به‌صورت متن ساده نمایش داده نمی‌شوند. فقط Fingerprint کلید SSH نمایش می‌یابد.</span>
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
        sshMode: '{{ old('auth_method', 'key') }}' === 'password' ? 'password' : 'existing_key',
        panelAuthMode: '{{ old('auth_method', 'api_token') }}' === 'password' ? 'password' : 'api_token',
        capabilities: {},

        // SSH key generation state
        keyGenerating: false,
        keyGenerated: false,
        keyGenError: '',
        generatedPublicKey: '',
        generatedPrivateKey: '',
        keyCopied: false,
        keyInstalled: false,
        rawPrivateKey: '{{ old('private_key', '') }}',

        capLabels: Object.entries({
            queue: 'صف کار', supervisor: 'Supervisor', redis: 'Redis',
            ssl: 'SSL', database: 'ساخت DB', storage: 'Storage Link',
            cron: 'زمانبند', docker: 'Docker', reboot: 'ریبوت',
        }),

        get needsPort()      { return ['ssh', 'sftp', 'docker'].includes(this.selectedType); },
        get needsSshAuth()   { return ['ssh', 'docker'].includes(this.selectedType); },
        get needsPanelAuth() { return ['cpanel', 'directadmin'].includes(this.selectedType); },
        get needsDomain()    { return ['cpanel', 'directadmin', 'sftp'].includes(this.selectedType); },
        get needsPath()      { return ['cpanel', 'directadmin', 'sftp'].includes(this.selectedType); },
        get defaultPort()    { return defaultPorts[this.selectedType] || 22; },

        // The auth_method value actually submitted to the server
        get resolvedAuthMethod() {
            if (this.needsSshAuth) {
                return this.sshMode === 'password' ? 'password' : 'key';
            }
            if (this.needsPanelAuth) {
                return this.panelAuthMode; // 'api_token' or 'password'
            }
            if (this.selectedType === 'sftp') {
                return 'sftp_password';
            }
            return 'key';
        },

        // The private_key value submitted — only for SSH key modes
        get resolvedPrivateKey() {
            if (!this.needsSshAuth) return '';
            if (this.sshMode === 'generate_key') return this.generatedPrivateKey;
            if (this.sshMode === 'existing_key') return this.rawPrivateKey;
            return '';
        },

        get hostLabel() {
            const labels = {
                ssh: 'آدرس IP / Hostname', directadmin: 'هاست پنل DirectAdmin',
                cpanel: 'هاست پنل cPanel', sftp: 'هاست SFTP', docker: 'هاست سرور Docker'
            };
            return labels[this.selectedType] || 'آدرس هاست / IP';
        },

        get hostPlaceholder() {
            const placeholders = {
                ssh: '185.x.x.x یا server.example.com', directadmin: 'panel.example.com',
                cpanel: 'cpanel.example.com', sftp: 'ftp.example.com', docker: 'docker.example.com'
            };
            return placeholders[this.selectedType] || 'example.com';
        },

        selectType(type) {
            this.selectedType = type;
            this.capabilities = capabilityMap[type] || {};
            // Reset auth defaults
            if (['ssh', 'docker'].includes(type)) {
                this.sshMode = 'existing_key';
            }
            if (['cpanel', 'directadmin'].includes(type)) {
                this.panelAuthMode = 'api_token';
            }
        },

        async generateKey() {
            this.keyGenerating = true;
            this.keyGenError   = '';
            try {
                const resp = await fetch('{{ route('admin.infrastructure.customer-deployment.servers.generate-ssh-key') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await resp.json();
                if (data.success) {
                    this.generatedPrivateKey = data.private_key;
                    this.generatedPublicKey  = data.public_key;
                    this.keyGenerated        = true;
                } else {
                    this.keyGenError = data.error || 'خطا در تولید کلید';
                }
            } catch (e) {
                this.keyGenError = 'خطای شبکه: ' + e.message;
            } finally {
                this.keyGenerating = false;
            }
        },

        async copyPublicKey() {
            try {
                await navigator.clipboard.writeText(this.generatedPublicKey);
                this.keyCopied = true;
                setTimeout(() => { this.keyCopied = false; }, 3000);
            } catch (e) {
                // Fallback for non-HTTPS
                const el = document.createElement('textarea');
                el.value = this.generatedPublicKey;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                this.keyCopied = true;
                setTimeout(() => { this.keyCopied = false; }, 3000);
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
