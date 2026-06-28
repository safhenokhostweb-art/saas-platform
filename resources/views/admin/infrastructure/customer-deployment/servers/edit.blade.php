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
    $currentAuthMethod = $server->auth_method ?? 'key';
    $isSshDriver = in_array($server->type, ['ssh', 'docker']);
    $isPanelDriver = in_array($server->type, ['cpanel', 'directadmin']);
    $isPasswordAuth = $currentAuthMethod === 'password';
    $isApiTokenAuth = $currentAuthMethod === 'api_token';
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

    {{-- PART 5 — Migration Wizard Banner (SSH password servers only) --}}
    @if($isSshDriver && $isPasswordAuth)
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-5" x-data="{ showMigrate: false }">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-indigo-900">مهاجرت به کلید SSH پیشنهاد می‌شود</p>
                <p class="text-xs text-indigo-700 mt-0.5">این سرور از رمز عبور SSH استفاده می‌کند. کلید SSH امن‌تر و پایدارتر است.</p>
            </div>
            <button type="button" @click="showMigrate = !showMigrate"
                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium shrink-0 transition-colors">
                مهاجرت به کلید SSH
            </button>
        </div>

        {{-- Migration Wizard --}}
        <div x-show="showMigrate" x-cloak x-transition class="mt-4 border-t border-indigo-200 pt-4"
             x-data="sshMigrationWizard()">

            {{-- Step 1: Generate --}}
            <div x-show="step === 1">
                <p class="text-sm font-semibold text-indigo-900 mb-3">مرحله ۱ — تولید کلید Ed25519</p>
                <button type="button" @click="generate()" :disabled="generating"
                    class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium flex items-center gap-2 transition-colors">
                    <svg x-show="!generating" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <svg x-show="generating" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span x-text="generating ? 'در حال تولید...' : 'تولید کلید Ed25519'"></span>
                </button>
                <div x-show="genError" class="mt-2 text-xs text-red-600" x-text="genError"></div>
            </div>

            {{-- Step 2+3: Show & Copy Public Key --}}
            <div x-show="step === 2">
                <p class="text-sm font-semibold text-indigo-900 mb-2">مرحله ۲ — کلید عمومی را کپی کنید</p>
                <div class="bg-gray-900 rounded-lg p-3 font-mono text-xs text-green-400 break-all mb-2" x-text="publicKey"></div>
                <button type="button" @click="copyKey()"
                    class="text-xs px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 font-medium flex items-center gap-1 mb-3">
                    <span x-text="copied ? '✓ کپی شد' : 'کپی کردن'"></span>
                </button>
                <p class="text-xs text-indigo-800 mb-4">کلید عمومی بالا را در فایل <code class="bg-indigo-100 px-1 rounded">~/.ssh/authorized_keys</code> سرور اضافه کنید.</p>
                <button type="button" @click="step = 3"
                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">
                    نصب کردم — ادامه
                </button>
            </div>

            {{-- Step 3: Test --}}
            <div x-show="step === 3">
                <p class="text-sm font-semibold text-indigo-900 mb-2">مرحله ۳ — تست اتصال</p>
                <p class="text-xs text-indigo-700 mb-3">کلید خصوصی به‌صورت خودکار در فرم زیر وارد شده. فرم را ذخیره کنید تا مقصد با کلید SSH به‌روز شود، سپس اتصال را تست کنید.</p>
                <div class="flex items-center gap-2 p-2 bg-green-50 border border-green-200 rounded-lg">
                    <svg class="w-4 h-4 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span class="text-xs text-green-800 font-medium">کلید خصوصی در فرم بارگذاری شد — ذخیره کنید تا فعال شود</span>
                </div>
            </div>

        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.servers.update', $server) }}"
          x-data="editServerForm()">
        @csrf @method('PUT')

        {{-- Hidden auth_method for panel drivers (managed by Alpine) --}}
        @if($isPanelDriver)
        <input type="hidden" name="auth_method" x-bind:value="panelAuthMode">
        @endif

        {{-- Hidden private_key for SSH migration wizard --}}
        @if($isSshDriver)
        <input type="hidden" name="private_key" x-bind:value="migratedPrivateKey">
        @endif

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
            @if($isPanelDriver)
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
            @if($isSshDriver)
            <div>
                <label class="form-label mb-3 block">روش احراز هویت SSH</label>

                {{-- Current auth method badge --}}
                <div class="mb-3 flex items-center gap-2">
                    <span class="text-xs text-gray-500">روش فعلی:</span>
                    @if($currentAuthMethod === 'key')
                        <span class="text-xs bg-green-100 text-green-700 border border-green-200 px-2 py-0.5 rounded font-medium">کلید SSH</span>
                    @else
                        <span class="text-xs bg-amber-100 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-medium">رمز عبور SSH</span>
                    @endif
                    @if($server->key_fingerprint)
                        <span class="text-xs font-mono text-gray-400">{{ $server->key_fingerprint }}</span>
                    @endif
                </div>

                <div x-data="{ sshEditMode: '{{ $currentAuthMethod === 'key' ? 'key' : 'password' }}' }">

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button type="button" x-on:click="sshEditMode = 'key'; $dispatch('ssh-mode-change', { mode: 'key' })"
                            :class="sshEditMode === 'key' ? 'ring-2 ring-indigo-500 border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-3 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900">کلید خصوصی SSH</span>
                            </div>
                            <p class="text-xs text-gray-500">امن‌تر — توصیه شده</p>
                            <div x-show="sshEditMode === 'key'" class="absolute top-2 left-2 w-4 h-4 bg-indigo-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>

                        <button type="button" x-on:click="sshEditMode = 'password'; $dispatch('ssh-mode-change', { mode: 'password' })"
                            :class="sshEditMode === 'password' ? 'ring-2 ring-gray-500 border-gray-400 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                            class="relative p-3 rounded-xl border-2 text-right transition-all">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="text-sm font-semibold text-gray-900">رمز عبور SSH</span>
                            </div>
                            <p class="text-xs text-gray-500">کمتر امن</p>
                            <div x-show="sshEditMode === 'password'" class="absolute top-2 left-2 w-4 h-4 bg-gray-600 rounded-full flex items-center justify-center">
                                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>
                    </div>

                    <input type="hidden" name="auth_method" :value="sshEditMode">

                    {{-- SSH Key (input + generate new option) --}}
                    <div x-show="sshEditMode === 'key'" x-cloak>
                        <div class="space-y-3" x-data="{ keyInputMode: 'paste' }">

                            <div class="flex gap-3 text-xs mb-2">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="paste" x-model="keyInputMode" class="text-indigo-600">
                                    <span class="text-gray-700">وارد کردن کلید موجود</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="generate" x-model="keyInputMode" class="text-green-600">
                                    <span class="text-gray-700">تولید کلید جدید</span>
                                </label>
                            </div>

                            {{-- Paste mode --}}
                            <div x-show="keyInputMode === 'paste'" x-cloak>
                                <label class="form-label">
                                    کلید خصوصی SSH جدید
                                    <span class="text-gray-400 font-normal text-xs mr-1">(خالی = بدون تغییر)</span>
                                </label>
                                <textarea name="private_key" rows="6"
                                    placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;برای بازنویسی کلید موجود، کلید جدید را وارد کنید&#10;-----END OPENSSH PRIVATE KEY-----"
                                    class="form-control font-mono text-xs @error('private_key') border-red-400 @enderror">{{ old('private_key') }}</textarea>
                                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                    کلید موجود با رمزگذاری ذخیره است — خالی گذاشتن آن را حفظ می‌کند
                                </p>
                                @error('private_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                            {{-- Generate mode --}}
                            <div x-show="keyInputMode === 'generate'" x-cloak x-data="inlineKeyGen()">

                                <div x-show="!generated">
                                    <button type="button" @click="gen()" :disabled="running"
                                        class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium flex items-center gap-2 transition-colors">
                                        <svg x-show="!running" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg x-show="running" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span x-text="running ? 'در حال تولید...' : 'تولید کلید Ed25519'"></span>
                                    </button>
                                    <div x-show="err" class="mt-2 text-xs text-red-600" x-text="err"></div>
                                </div>

                                <div x-show="generated" x-cloak class="space-y-3">
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-2 text-sm text-green-800">
                                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        کلید جدید ایجاد شد — با ذخیره فرم رمزگذاری می‌شود
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs text-gray-600 font-medium">کلید عمومی (برای authorized_keys سرور):</span>
                                            <button type="button" @click="cp()"
                                                class="text-xs px-2 py-1 rounded border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100">
                                                <span x-text="cpd ? '✓ کپی شد' : 'کپی'"></span>
                                            </button>
                                        </div>
                                        <div class="bg-gray-900 rounded p-2 font-mono text-xs text-green-400 break-all" x-text="pub"></div>
                                    </div>
                                    <input type="hidden" name="private_key" :value="priv">
                                    <p class="text-xs text-blue-700 bg-blue-50 p-2 rounded">کلید عمومی را در <code class="bg-blue-100 px-1">~/.ssh/authorized_keys</code> سرور اضافه کنید، سپس فرم را ذخیره کنید.</p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- SSH Password --}}
                    <div x-show="sshEditMode === 'password'" x-cloak>
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
            </div>
            @endif

            {{-- ─── cPanel / DirectAdmin: Auth Method Selector ─── --}}
            @if($isPanelDriver)
            <div>
                <label class="form-label mb-3 block">روش احراز هویت</label>

                {{-- Current auth badge --}}
                <div class="mb-3 flex items-center gap-2">
                    <span class="text-xs text-gray-500">روش فعلی:</span>
                    @if($isApiTokenAuth)
                        <span class="text-xs bg-orange-100 text-orange-700 border border-orange-200 px-2 py-0.5 rounded font-medium">API Token</span>
                    @elseif($isPasswordAuth)
                        <span class="text-xs bg-gray-100 text-gray-700 border border-gray-200 px-2 py-0.5 rounded font-medium">نام کاربری + رمز عبور</span>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    {{-- API Token --}}
                    <button type="button" x-on:click="panelAuthMode = 'api_token'"
                        :class="panelAuthMode === 'api_token' ? 'ring-2 ring-orange-500 border-orange-300 bg-orange-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                        class="relative p-3 rounded-xl border-2 text-right transition-all">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-4 h-4 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            <span class="text-sm font-semibold text-gray-900">{{ $server->type === 'cpanel' ? 'API Token' : 'API Key' }}</span>
                        </div>
                        <p class="text-xs text-gray-500">پیشنهادی — امن‌تر</p>
                        <span class="absolute top-2 left-2 text-xs bg-orange-100 text-orange-700 px-1 py-0.5 rounded">پیشنهادی</span>
                        <div x-show="panelAuthMode === 'api_token'" class="absolute top-2 left-2 w-4 h-4 bg-orange-600 rounded-full flex items-center justify-center">
                            <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                    </button>

                    {{-- Password --}}
                    <button type="button" x-on:click="panelAuthMode = 'password'"
                        :class="panelAuthMode === 'password' ? 'ring-2 ring-gray-500 border-gray-400 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                        class="relative p-3 rounded-xl border-2 text-right transition-all">
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

                {{-- API Token field --}}
                <div x-show="panelAuthMode === 'api_token'" x-cloak>
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

                {{-- Panel Password field --}}
                <div x-show="panelAuthMode === 'password'" x-cloak>
                    <label class="form-label">
                        رمز عبور پنل جدید
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
function editServerForm() {
    return {
        panelAuthMode: '{{ $currentAuthMethod === 'password' ? 'password' : 'api_token' }}',
        migratedPrivateKey: '',
    };
}

function sshMigrationWizard() {
    return {
        step: 1,
        generating: false,
        generated: false,
        genError: '',
        publicKey: '',
        privateKey: '',
        copied: false,

        async generate() {
            this.generating = true;
            this.genError   = '';
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
                    this.publicKey  = data.public_key;
                    this.privateKey = data.private_key;
                    // Inject into the form's hidden field
                    const hidden = document.querySelector('input[name="private_key"]');
                    if (hidden) hidden.value = data.private_key;
                    // Change auth_method to key
                    const authField = document.querySelector('input[name="auth_method"]');
                    if (authField) authField.value = 'key';
                    this.step = 2;
                } else {
                    this.genError = data.error || 'خطا در تولید کلید';
                }
            } catch (e) {
                this.genError = 'خطای شبکه: ' + e.message;
            } finally {
                this.generating = false;
            }
        },

        async copyKey() {
            try {
                await navigator.clipboard.writeText(this.publicKey);
            } catch {
                const el = document.createElement('textarea');
                el.value = this.publicKey;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
            }
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 3000);
        },
    };
}

function inlineKeyGen() {
    return {
        running: false,
        generated: false,
        err: '',
        pub: '',
        priv: '',
        cpd: false,

        async gen() {
            this.running = true;
            this.err     = '';
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
                    this.pub  = data.public_key;
                    this.priv = data.private_key;
                    this.generated = true;
                } else {
                    this.err = data.error || 'خطا';
                }
            } catch (e) {
                this.err = e.message;
            } finally {
                this.running = false;
            }
        },

        async cp() {
            try { await navigator.clipboard.writeText(this.pub); } catch {
                const el = document.createElement('textarea');
                el.value = this.pub;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
            }
            this.cpd = true;
            setTimeout(() => { this.cpd = false; }, 3000);
        },
    };
}

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
