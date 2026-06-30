@extends('layouts.admin')

@section('title', 'ویزارد بازیابی — Disaster Recovery')

@section('content')
<div class="p-6 space-y-6" dir="rtl"
     x-data="restoreWizard()"
     x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویزارد بازیابی (Restore Wizard)</h1>
            <p class="text-sm text-gray-500 mt-1">بازیابی پلتفرم یا تِنِنت از بکاپ‌های Arvan Object Storage</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.disaster-recovery.logs') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                تاریخچه بازیابی
            </a>
        </div>
    </div>

    {{-- Active Lock Warning --}}
    @if($lockActive)
    <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-orange-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="font-semibold text-orange-800">یک بازیابی در حال اجرا است</p>
            <p class="text-sm text-orange-700 mt-1">قفل Redis فعال: <code class="font-mono bg-orange-100 px-1 rounded">{{ $lockValue }}</code></p>
            <button onclick="document.getElementById('abort-form').submit()"
                    class="mt-2 text-sm text-red-600 hover:text-red-800 underline">
                لغو بازیابی و آزاد کردن قفل
            </button>
            <form id="abort-form" action="{{ route('admin.disaster-recovery.abort') }}" method="POST" class="hidden">@csrf</form>
        </div>
    </div>
    @endif

    {{-- Step Indicator --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between relative">
            <div class="absolute top-1/2 right-0 left-0 h-px bg-gray-200 -translate-y-1/2 z-0"></div>
            @php
            $steps = [
                1 => 'انتخاب نوع',
                2 => 'انتخاب هدف',
                3 => 'انتخاب بکاپ',
                4 => 'اعتبارسنجی',
                5 => 'شبیه‌سازی',
                6 => 'تأیید',
                7 => 'نتیجه',
            ];
            @endphp
            @foreach($steps as $n => $label)
            <div class="relative z-10 flex flex-col items-center gap-1"
                 x-bind:class="{ 'opacity-100': currentStep >= {{ $n }}, 'opacity-40': currentStep < {{ $n }} }">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors"
                     x-bind:class="currentStep > {{ $n }} ? 'bg-green-500 text-white' : (currentStep === {{ $n }} ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600')">
                    <template x-if="currentStep > {{ $n }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </template>
                    <template x-if="currentStep <= {{ $n }}">
                        <span>{{ $n }}</span>
                    </template>
                </div>
                <span class="text-xs text-gray-600 whitespace-nowrap">{{ $label }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Wizard Body --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

        {{-- ─── Step 1: Select Type ─────────────────────────────────────────── --}}
        <div x-show="currentStep === 1" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۱ — نوع بازیابی را انتخاب کنید</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @php
                $types = [
                    ['value' => 'platform_database', 'label' => 'بازیابی دیتابیس پلتفرم', 'desc' => 'بازیابی کامل دیتابیس saas_platform از بکاپ Arvan', 'color' => 'red', 'icon' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4'],
                    ['value' => 'platform_storage', 'label' => 'بازیابی فایل‌های پلتفرم', 'desc' => 'بازیابی storage/app/public از آرشیو Arvan', 'color' => 'yellow', 'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
                    ['value' => 'tenant_database', 'label' => 'بازیابی دیتابیس تِنِنت', 'desc' => 'بازیابی داده‌های یک سایت مشتری (site_id) بدون تأثیر بر بقیه', 'color' => 'blue', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                    ['value' => 'tenant_storage', 'label' => 'بازیابی فایل‌های تِنِنت', 'desc' => 'بازیابی فایل‌های آپلودی یک سایت مشتری', 'color' => 'green', 'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ];
                $colorMap = [
                    'red'    => 'border-red-300 bg-red-50 hover:bg-red-100',
                    'yellow' => 'border-yellow-300 bg-yellow-50 hover:bg-yellow-100',
                    'blue'   => 'border-blue-300 bg-blue-50 hover:bg-blue-100',
                    'green'  => 'border-green-300 bg-green-50 hover:bg-green-100',
                ];
                $selectedMap = [
                    'red'    => 'ring-2 ring-red-500 border-red-500',
                    'yellow' => 'ring-2 ring-yellow-500 border-yellow-500',
                    'blue'   => 'ring-2 ring-blue-500 border-blue-500',
                    'green'  => 'ring-2 ring-green-500 border-green-500',
                ];
                @endphp
                @foreach($types as $t)
                <button type="button"
                        x-on:click="selectType('{{ $t['value'] }}')"
                        class="text-right p-4 rounded-xl border-2 transition {{ $colorMap[$t['color']] }}"
                        x-bind:class="form.type === '{{ $t['value'] }}' ? '{{ $selectedMap[$t['color']] }}' : ''">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 mt-0.5 text-{{ $t['color'] }}-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $t['icon'] }}"/>
                        </svg>
                        <div>
                            <p class="font-semibold text-gray-800">{{ $t['label'] }}</p>
                            <p class="text-sm text-gray-600 mt-1">{{ $t['desc'] }}</p>
                        </div>
                    </div>
                </button>
                @endforeach
            </div>
            <div class="flex justify-end pt-4">
                <button x-on:click="goToStep(2)" x-bind:disabled="!form.type"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    مرحله بعد →
                </button>
            </div>
        </div>

        {{-- ─── Step 2: Select Target ───────────────────────────────────────── --}}
        <div x-show="currentStep === 2" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۲ — انتخاب هدف بازیابی</h2>

            {{-- Platform types: no tenant selection needed --}}
            <template x-if="!isTenantType()">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-blue-800 font-medium">بازیابی پلتفرم انتخاب شد</p>
                    <p class="text-sm text-blue-600 mt-1">این بازیابی بر کل پلتفرم تأثیر می‌گذارد. در مرحله بعد فایل بکاپ را انتخاب کنید.</p>
                </div>
            </template>

            {{-- Tenant types: select site --}}
            <template x-if="isTenantType()">
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">کدام سایت مشتری را می‌خواهید بازیابی کنید؟</p>
                    <div class="relative">
                        <input type="text" placeholder="جستجو بر اساس نام یا دامنه..."
                               x-model="siteSearch"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"/>
                    </div>
                    <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-xl divide-y divide-gray-100">
                        @foreach($sites as $site)
                        <button type="button"
                                x-show="!siteSearch || '{{ strtolower($site->name) }}'.includes(siteSearch.toLowerCase()) || '{{ strtolower($site->domain) }}'.includes(siteSearch.toLowerCase())"
                                x-on:click="selectSite({{ $site->id }}, '{{ addslashes($site->name) }}', '{{ $site->domain }}')"
                                class="w-full text-right px-4 py-3 hover:bg-gray-50 flex items-center justify-between transition"
                                x-bind:class="form.site_id === {{ $site->id }} ? 'bg-blue-50' : ''">
                            <div>
                                <span class="font-medium text-gray-800">{{ $site->name }}</span>
                                <span class="text-sm text-gray-500 mr-2">{{ $site->domain }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">ID: {{ $site->id }}</span>
                                <template x-if="form.site_id === {{ $site->id }}">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                            </div>
                        </button>
                        @endforeach
                    </div>
                    <template x-if="form.site_id">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">
                            سایت انتخابی: <strong x-text="selectedSiteName"></strong>
                            (site_id: <span x-text="form.site_id"></span>)
                        </div>
                    </template>
                </div>
            </template>

            <div class="flex justify-between pt-4">
                <button x-on:click="goToStep(1)" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                    ← قبلی
                </button>
                <button x-on:click="loadBackups()" x-bind:disabled="isTenantType() && !form.site_id"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    مرحله بعد →
                </button>
            </div>
        </div>

        {{-- ─── Step 3: Select Backup File ──────────────────────────────────── --}}
        <div x-show="currentStep === 3" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۳ — انتخاب فایل بکاپ</h2>
            <p class="text-sm text-gray-500">
                مسیر Arvan: <code class="font-mono bg-gray-100 px-2 py-0.5 rounded" x-text="backupPrefix"></code>
            </p>

            <template x-if="loadingBackups">
                <div class="flex items-center gap-3 py-8 justify-center">
                    <svg class="animate-spin w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span class="text-gray-600">در حال بارگذاری لیست بکاپ‌ها از Arvan...</span>
                </div>
            </template>

            <template x-if="!loadingBackups && browseError">
                <div class="rounded-xl bg-red-50 border border-red-200 p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-red-800">خطا در دریافت لیست بکاپ‌ها</p>
                            <p class="text-sm text-red-700 mt-1 font-mono break-all" x-text="browseError"></p>
                        </div>
                    </div>
                    <button x-on:click="loadBackups()" type="button"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition">
                        تلاش مجدد
                    </button>
                </div>
            </template>

            <template x-if="!loadingBackups && !browseError && backups.length === 0">
                <div class="text-center py-10">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                    <p class="text-gray-500">هیچ فایل بکاپی در این مسیر یافت نشد</p>
                    <p class="text-sm text-gray-400 mt-1">مطمئن شوید که دستور <code class="font-mono">php artisan db:backup</code> اجرا شده است</p>
                    <button x-on:click="loadBackups()" type="button" class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                        بارگذاری مجدد
                    </button>
                </div>
            </template>

            <template x-if="!loadingBackups && backups.length > 0">
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-right px-4 py-3 font-medium text-gray-700">نام فایل</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-700">اندازه</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-700">تاریخ</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(file, idx) in backups" :key="file.path">
                                <tr class="hover:bg-gray-50 transition"
                                    x-bind:class="form.backup_file === file.path ? 'bg-blue-50' : ''">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span class="font-mono text-xs text-gray-700" x-text="file.filename"></span>
                                            <template x-if="idx === 0">
                                                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">جدیدترین</span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600" x-text="file.size_human"></td>
                                    <td class="px-4 py-3 text-gray-600" x-text="file.date_human"></td>
                                    <td class="px-4 py-3 text-left">
                                        <button type="button"
                                                x-on:click="selectBackup(file)"
                                                class="px-3 py-1 rounded-lg text-xs font-medium transition"
                                                x-bind:class="form.backup_file === file.path ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                                            <span x-text="form.backup_file === file.path ? '✓ انتخاب شد' : 'انتخاب'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <div class="flex justify-between pt-4">
                <button x-on:click="goToStep(2)" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                    ← قبلی
                </button>
                <button x-on:click="runValidation()" x-bind:disabled="!form.backup_file"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    اعتبارسنجی →
                </button>
            </div>
        </div>

        {{-- ─── Step 4: Validation Results ─────────────────────────────────── --}}
        <div x-show="currentStep === 4" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۴ — نتایج اعتبارسنجی</h2>

            <template x-if="validating">
                <div class="flex items-center gap-3 py-8 justify-center">
                    <svg class="animate-spin w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>در حال اعتبارسنجی فایل بکاپ...</span>
                </div>
            </template>

            <template x-if="!validating && validationResult">
                <div class="space-y-4">
                    {{-- Overall status --}}
                    <div class="rounded-xl p-4 flex items-center gap-3"
                         x-bind:class="validationResult.valid ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                        <template x-if="validationResult.valid">
                            <svg class="w-6 h-6 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </template>
                        <template x-if="!validationResult.valid">
                            <svg class="w-6 h-6 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </template>
                        <div>
                            <p class="font-semibold"
                               x-bind:class="validationResult.valid ? 'text-green-800' : 'text-red-800'"
                               x-text="validationResult.valid ? 'اعتبارسنجی موفق — آماده برای شبیه‌سازی' : 'اعتبارسنجی ناموفق — برخی بررسی‌ها ناموفق بودند'">
                            </p>
                        </div>
                    </div>

                    {{-- Checks --}}
                    <div class="space-y-2">
                        <template x-for="check in validationResult.checks">
                            <div class="flex items-center gap-3 p-3 rounded-lg border"
                                 x-bind:class="check.result === 'pass' ? 'border-green-200 bg-green-50' : (check.result === 'warn' ? 'border-yellow-200 bg-yellow-50' : 'border-red-200 bg-red-50')">
                                <template x-if="check.result === 'pass'">
                                    <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                                <template x-if="check.result === 'warn'">
                                    <svg class="w-4 h-4 text-yellow-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </template>
                                <template x-if="check.result === 'fail'">
                                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </template>
                                <div>
                                    <p class="font-medium text-sm text-gray-800" x-text="check.check"></p>
                                    <p class="text-xs text-gray-600" x-text="check.detail"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Metadata --}}
                    <template x-if="validationResult.metadata">
                        <div class="grid grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4 text-sm">
                            <div>
                                <span class="text-gray-500">اندازه بکاپ:</span>
                                <span class="font-medium mr-2" x-text="validationResult.metadata.size_human"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">تاریخ ایجاد:</span>
                                <span class="font-medium mr-2" x-text="validationResult.metadata.last_modified ? new Date(validationResult.metadata.last_modified * 1000).toLocaleDateString('fa-IR') : '—'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div class="flex justify-between pt-4">
                <button x-on:click="goToStep(3)" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                    ← قبلی
                </button>
                <button x-on:click="runDryRun()" x-bind:disabled="!validationResult || !validationResult.valid || validating"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    شبیه‌سازی →
                </button>
            </div>
        </div>

        {{-- ─── Step 5: Dry Run Results ─────────────────────────────────────── --}}
        <div x-show="currentStep === 5" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۵ — نتایج شبیه‌سازی (Dry Run)</h2>
            <p class="text-sm text-gray-500">این شبیه‌سازی است. هیچ تغییری در دیتابیس یا فایل‌ها ایجاد نشده است.</p>

            <template x-if="runningDryRun">
                <div class="flex items-center gap-3 py-8 justify-center">
                    <svg class="animate-spin w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>در حال شبیه‌سازی...</span>
                </div>
            </template>

            <template x-if="!runningDryRun && dryRunResult">
                <div class="space-y-4">
                    {{-- Impact --}}
                    <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                        <h3 class="font-semibold text-gray-700">تأثیر بازیابی</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-gray-500">دامنه تأثیر</p>
                                <p class="font-medium mt-1" x-text="dryRunResult.impact.scope"></p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-gray-500">Downtime</p>
                                <p class="font-medium mt-1" x-text="dryRunResult.impact.downtime"></p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <p class="text-gray-500">داده در معرض خطر</p>
                                <p class="font-medium mt-1 text-xs" x-text="dryRunResult.impact.data_at_risk"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Warnings --}}
                    <template x-if="dryRunResult.warnings && dryRunResult.warnings.length > 0">
                        <div class="space-y-2">
                            <h3 class="font-semibold text-gray-700">هشدارها</h3>
                            <template x-for="w in dryRunResult.warnings">
                                <div class="flex items-start gap-3 p-3 rounded-lg"
                                     x-bind:class="w.level === 'critical' ? 'bg-red-50 border border-red-200' : (w.level === 'high' ? 'bg-orange-50 border border-orange-200' : 'bg-yellow-50 border border-yellow-200')">
                                    <svg class="w-4 h-4 mt-0.5 shrink-0"
                                         x-bind:class="w.level === 'critical' ? 'text-red-600' : (w.level === 'high' ? 'text-orange-600' : 'text-yellow-600')"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <div>
                                        <span class="font-mono text-xs uppercase px-1.5 py-0.5 rounded"
                                              x-bind:class="w.level === 'critical' ? 'bg-red-200 text-red-800' : (w.level === 'high' ? 'bg-orange-200 text-orange-800' : 'bg-yellow-200 text-yellow-800')"
                                              x-text="w.level">
                                        </span>
                                        <p class="text-sm text-gray-700 mt-1" x-text="w.message"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Steps --}}
                    <div class="space-y-2">
                        <h3 class="font-semibold text-gray-700">مراحل اجرا</h3>
                        <div class="space-y-1">
                            <template x-for="step in dryRunResult.steps">
                                <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                                    <span class="w-6 h-6 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                          x-text="step.step"></span>
                                    <span class="text-sm text-gray-700" x-text="step.action"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Safe to run badge --}}
                    <div class="rounded-xl p-4 flex items-center gap-3"
                         x-bind:class="dryRunResult.safe_to_run ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                        <template x-if="dryRunResult.safe_to_run">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </template>
                        <p class="font-semibold"
                           x-bind:class="dryRunResult.safe_to_run ? 'text-green-800' : 'text-red-800'"
                           x-text="dryRunResult.safe_to_run ? 'ایمن برای اجرا — می‌توانید ادامه دهید' : 'اجرا توصیه نمی‌شود — مشکلات حیاتی وجود دارد'">
                        </p>
                    </div>
                </div>
            </template>

            <div class="flex justify-between pt-4">
                <button x-on:click="goToStep(4)" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                    ← قبلی
                </button>
                <button x-on:click="goToStep(6)" x-bind:disabled="!dryRunResult || !dryRunResult.safe_to_run || runningDryRun"
                        class="px-6 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    ادامه به تأیید →
                </button>
            </div>
        </div>

        {{-- ─── Step 6: Confirmation ────────────────────────────────────────── --}}
        <div x-show="currentStep === 6" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۶ — تأیید نهایی</h2>

            <div class="bg-red-50 border-2 border-red-300 rounded-xl p-5 space-y-4">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-bold text-red-800 text-lg">⚠️ هشدار — این عملیات برگشت‌ناپذیر است</p>
                        <p class="text-sm text-red-700 mt-2">بازیابی داده‌های موجود را با داده‌های بکاپ جایگزین خواهد کرد. قبل از ادامه مطمئن شوید که:</p>
                        <ul class="text-sm text-red-700 mt-2 space-y-1 list-disc list-inside">
                            <li>نسخه‌ای از وضعیت فعلی دارید</li>
                            <li>فایل بکاپ صحیح انتخاب شده است</li>
                            <li>این عملیات در زمان مناسب انجام می‌شود</li>
                        </ul>
                    </div>
                </div>

                {{-- Summary --}}
                <div class="bg-white rounded-lg p-4 space-y-2 text-sm border border-red-200">
                    <div class="flex justify-between">
                        <span class="text-gray-600">نوع بازیابی:</span>
                        <span class="font-medium" x-text="getTypeLabel()"></span>
                    </div>
                    <div class="flex justify-between" x-show="form.site_id">
                        <span class="text-gray-600">سایت هدف:</span>
                        <span class="font-medium" x-text="selectedSiteName + ' (ID: ' + form.site_id + ')'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">فایل بکاپ:</span>
                        <span class="font-mono text-xs" x-text="form.backup_file"></span>
                    </div>
                </div>

                {{-- Platform DB extra confirmation --}}
                <template x-if="form.type === 'platform_database'">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-red-800">برای بازیابی دیتابیس پلتفرم، عبارت <code class="font-mono bg-red-200 px-1">RESTORE-PLATFORM</code> را تایپ کنید:</p>
                        <input type="text" x-model="confirmText" placeholder="RESTORE-PLATFORM"
                               class="w-full border-2 border-red-300 rounded-lg px-4 py-2 font-mono text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"/>
                    </div>
                </template>

                {{-- Confirmation checkbox --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="form.confirmed" class="mt-0.5 w-4 h-4 text-red-600 border-gray-300 rounded"/>
                    <span class="text-sm text-red-800 font-medium">
                        تأیید می‌کنم که این بازیابی را به صورت آگاهانه و با مسئولیت کامل انجام می‌دهم.
                    </span>
                </label>

                {{-- Storage merge strategy --}}
                <template x-if="form.type === 'platform_storage' || form.type === 'tenant_storage'">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-gray-700">استراتژی ادغام:</p>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="form.merge_strategy" value="merge" class="text-blue-600"/>
                                <span class="text-sm">ادغام (توصیه شده) — فقط فایل‌های گم‌شده اضافه می‌شوند</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="form.merge_strategy" value="overwrite" class="text-red-600"/>
                                <span class="text-sm">بازنویسی — همه فایل‌ها جایگزین می‌شوند</span>
                            </label>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-between pt-4">
                <button x-on:click="goToStep(5)" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                    ← قبلی
                </button>
                <button x-on:click="executeRestore()"
                        x-bind:disabled="!form.confirmed || (form.type === 'platform_database' && confirmText !== 'RESTORE-PLATFORM') || executing"
                        class="px-6 py-2 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-2">
                    <template x-if="executing">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </template>
                    <span x-text="executing ? 'در حال شروع...' : '🔴 شروع بازیابی'"></span>
                </button>
            </div>
        </div>

        {{-- ─── Step 7: Execution Progress & Result ────────────────────────── --}}
        <div x-show="currentStep === 7" x-transition class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">مرحله ۷ — اجرای بازیابی</h2>

            {{-- Progress bar --}}
            <div class="space-y-2">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>پیشرفت:</span>
                    <span x-text="progressPercent + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-300"
                         x-bind:class="restoreStatus === 'success' ? 'bg-green-500' : (restoreStatus === 'error' ? 'bg-red-500' : 'bg-blue-500')"
                         x-bind:style="'width: ' + progressPercent + '%'">
                    </div>
                </div>
            </div>

            {{-- Live log --}}
            <div class="bg-gray-900 rounded-xl p-4 font-mono text-sm h-64 overflow-y-auto space-y-1" id="restore-log">
                <template x-for="event in progressEvents">
                    <div class="flex items-start gap-2"
                         x-bind:class="event.status === 'error' ? 'text-red-400' : (event.status === 'success' ? 'text-green-400' : (event.status === 'warn' ? 'text-yellow-400' : 'text-gray-300'))">
                        <span class="text-gray-500 shrink-0" x-text="event.time || ''"></span>
                        <span x-text="event.message"></span>
                    </div>
                </template>
                <template x-if="progressEvents.length === 0">
                    <div class="text-gray-500">در انتظار دریافت اطلاعات...</div>
                </template>
            </div>

            {{-- Final result --}}
            <template x-if="restoreStatus === 'success'">
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="font-bold text-green-800">بازیابی با موفقیت انجام شد</p>
                        <p class="text-sm text-green-700 mt-1">سیستم بازیابی شد. لطفاً صحت داده‌ها را بررسی کنید.</p>
                    </div>
                </div>
            </template>

            <template x-if="restoreStatus === 'error'">
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="font-bold text-red-800">بازیابی ناموفق بود</p>
                    </div>
                    <p class="text-sm text-red-700">Snapshot قبل از بازیابی در <code class="font-mono">/tmp/</code> موجود است (۲۴ ساعت). برای بازیابی دستی از <a href="{{ route('admin.disaster-recovery.logs') }}" class="underline">تاریخچه بازیابی</a> استفاده کنید.</p>
                </div>
            </template>

            <div class="flex justify-between pt-4">
                <button x-on:click="resetWizard()"
                        x-show="restoreStatus === 'success' || restoreStatus === 'error'"
                        class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                    شروع بازیابی جدید
                </button>
                <a href="{{ route('admin.disaster-recovery.logs') }}"
                   x-show="restoreStatus === 'success' || restoreStatus === 'error'"
                   class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition">
                    مشاهده لاگ بازیابی
                </a>
            </div>
        </div>
    </div>

    {{-- Recent Restore Logs --}}
    @if($recentLogs->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">آخرین بازیابی‌ها</h3>
            <a href="{{ route('admin.disaster-recovery.logs') }}" class="text-sm text-blue-600 hover:underline">مشاهده همه</a>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($recentLogs as $log)
            <div class="px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @php
                    $statusColor = match($log->status) {
                        'success' => 'bg-green-100 text-green-700',
                        'failed'  => 'bg-red-100 text-red-700',
                        'running' => 'bg-blue-100 text-blue-700',
                        default   => 'bg-gray-100 text-gray-700',
                    };
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $statusColor }}">
                        {{ $log->statusLabelFa() }}
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $log->typeLabelFa() }}</p>
                        <p class="text-xs text-gray-500">{{ basename($log->backup_file) }}
                            @if($log->site) · {{ $log->site->name }}@endif
                        </p>
                    </div>
                </div>
                <div class="text-left">
                    <p class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</p>
                    @if($log->dry_run)
                    <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">Dry Run</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
function restoreWizard() {
    return {
        currentStep: 1,
        form: {
            type: null,
            site_id: null,
            backup_file: null,
            confirmed: false,
            merge_strategy: 'merge',
        },
        selectedSiteName: '',
        siteSearch: '',
        backups: [],
        backupPrefix: '',
        browseError: null,
        loadingBackups: false,
        validating: false,
        validationResult: null,
        runningDryRun: false,
        dryRunResult: null,
        executing: false,
        confirmText: '',
        progressEvents: [],
        progressPercent: 0,
        restoreStatus: null, // 'success' | 'error' | null
        eventSource: null,
        restoreToken: null,

        init() {},

        isTenantType() {
            return this.form.type === 'tenant_database' || this.form.type === 'tenant_storage';
        },

        selectType(type) {
            this.form.type = type;
            this.form.site_id = null;
            this.form.backup_file = null;
            this.selectedSiteName = '';
            this.validationResult = null;
            this.dryRunResult = null;
        },

        selectSite(id, name, domain) {
            this.form.site_id = id;
            this.selectedSiteName = name;
        },

        goToStep(step) {
            this.currentStep = step;
        },

        getTypeLabel() {
            const labels = {
                platform_database: 'بازیابی دیتابیس پلتفرم',
                platform_storage:  'بازیابی فایل‌های پلتفرم',
                tenant_database:   'بازیابی دیتابیس تِنِنت',
                tenant_storage:    'بازیابی فایل‌های تِنِنت',
            };
            return labels[this.form.type] || this.form.type;
        },

        async loadBackups() {
            this.loadingBackups = true;
            this.backups = [];
            this.browseError = null;
            this.goToStep(3);

            try {
                const params = new URLSearchParams({ type: this.form.type });
                if (this.form.site_id) params.append('site_id', this.form.site_id);

                const res = await fetch(`{{ route('admin.disaster-recovery.browse') }}?${params}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                const rawText = await res.text();

                if (!res.ok) {
                    this.browseError = `خطای سرور ${res.status}: ${rawText.substring(0, 300)}`;
                    return;
                }

                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (jsonErr) {
                    this.browseError = `پاسخ سرور JSON نیست (${res.status}). شروع پاسخ: ${rawText.substring(0, 200)}`;
                    return;
                }

                if (data.error) {
                    this.browseError = data.error;
                    return;
                }

                this.backups = data.files || [];
                this.backupPrefix = data.prefix || '';

                if (this.backups.length === 0) {
                    this.browseError = null;
                }
            } catch (e) {
                this.browseError = `خطای شبکه: ${e.message}`;
                console.error('Browse failed:', e);
            } finally {
                this.loadingBackups = false;
            }
        },

        selectBackup(file) {
            this.form.backup_file = file.path;
        },

        async runValidation() {
            this.validating = true;
            this.validationResult = null;
            this.goToStep(4);

            try {
                const res = await fetch('{{ route('admin.disaster-recovery.validate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        backup_file: this.form.backup_file,
                        type: this.form.type,
                    }),
                });
                this.validationResult = await res.json();
            } catch (e) {
                console.error('Validation failed:', e);
            } finally {
                this.validating = false;
            }
        },

        async runDryRun() {
            this.runningDryRun = true;
            this.dryRunResult = null;
            this.goToStep(5);

            try {
                const res = await fetch('{{ route('admin.disaster-recovery.dry-run') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        type:        this.form.type,
                        backup_file: this.form.backup_file,
                        site_id:     this.form.site_id,
                    }),
                });
                this.dryRunResult = await res.json();
            } catch (e) {
                console.error('Dry run failed:', e);
            } finally {
                this.runningDryRun = false;
            }
        },

        async executeRestore() {
            this.executing = true;
            try {
                const payload = {
                    type:           this.form.type,
                    backup_file:    this.form.backup_file,
                    site_id:        this.form.site_id,
                    merge_strategy: this.form.merge_strategy,
                    confirmation:   this.form.confirmed ? '1' : '',
                    confirm_text:   this.confirmText,
                };

                const res = await fetch('{{ route('admin.disaster-recovery.execute') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok || data.error) {
                    alert('خطا: ' + (data.error || 'خطای ناشناخته'));
                    return;
                }

                this.restoreToken = data.token;
                this.goToStep(7);
                this.streamProgress(data.stream_url);

            } catch (e) {
                alert('خطا در ارسال درخواست: ' + e.message);
            } finally {
                this.executing = false;
            }
        },

        streamProgress(url) {
            this.progressEvents = [];
            this.progressPercent = 0;
            this.restoreStatus = null;

            if (this.eventSource) this.eventSource.close();

            this.eventSource = new EventSource(url);
            this.eventSource.onmessage = (e) => {
                const data = JSON.parse(e.data);
                this.progressEvents.push(data);

                if (data.progress !== undefined && data.progress !== null) {
                    this.progressPercent = data.progress;
                }

                if (data.step === 'done') {
                    this.restoreStatus = 'success';
                    this.progressPercent = 100;
                    this.eventSource.close();
                } else if (data.step === 'error') {
                    this.restoreStatus = 'error';
                    this.eventSource.close();
                }

                // Auto-scroll log to bottom
                this.$nextTick(() => {
                    const logEl = document.getElementById('restore-log');
                    if (logEl) logEl.scrollTop = logEl.scrollHeight;
                });
            };

            this.eventSource.onerror = () => {
                if (this.restoreStatus !== 'success' && this.restoreStatus !== 'error') {
                    this.progressEvents.push({ message: 'اتصال SSE قطع شد. در حال تلاش مجدد...', status: 'warn', time: '' });
                }
            };
        },

        resetWizard() {
            this.currentStep = 1;
            this.form = { type: null, site_id: null, backup_file: null, confirmed: false, merge_strategy: 'merge' };
            this.backups = [];
            this.browseError = null;
            this.validationResult = null;
            this.dryRunResult = null;
            this.progressEvents = [];
            this.progressPercent = 0;
            this.restoreStatus = null;
            this.confirmText = '';
            this.selectedSiteName = '';
            if (this.eventSource) this.eventSource.close();
        },
    };
}
</script>
@endpush
@endsection
