@extends('layouts.admin')

@section('header', 'مرکز احراز هویت')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مرکز احراز هویت</h1>
        <p class="text-sm text-gray-500">مدیریت کلیدهای SSH، توکن‌های API، Fingerprint و امنیت اعتبارنامه‌ها</p>
    </div>
    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.generate') }}"
       class="btn btn-primary text-sm flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
        تولید کلید SSH جدید
    </a>
</div>

{{-- Security Score --}}
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    {{-- Score card --}}
    <div class="sm:col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col items-center justify-center">
        <div class="text-3xl font-bold {{ $securityScore >= 80 ? 'text-green-600' : ($securityScore >= 50 ? 'text-amber-500' : 'text-red-500') }}">
            {{ $securityScore }}%
        </div>
        <div class="text-xs text-gray-500 mt-1 text-center">امتیاز امنیتی</div>
        <div class="mt-2 w-full bg-gray-100 rounded-full h-1.5">
            <div class="{{ $securityScore >= 80 ? 'bg-green-500' : ($securityScore >= 50 ? 'bg-amber-500' : 'bg-red-500') }} h-1.5 rounded-full transition-all"
                 style="width: {{ $securityScore }}%"></div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">{{ $sshKeyCount }}</div>
                <div class="text-xs text-gray-500">کلید SSH فعال</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">{{ $apiTokenCount }}</div>
                <div class="text-xs text-gray-500">توکن API ذخیره‌شده</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg {{ $passwordOnly > 0 ? 'bg-red-100' : 'bg-green-100' }} flex items-center justify-center">
                <svg class="w-5 h-5 {{ $passwordOnly > 0 ? 'text-red-600' : 'text-green-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $passwordOnly > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $passwordOnly }}</div>
                <div class="text-xs text-gray-500">مقصد فقط رمز عبور</div>
            </div>
        </div>
    </div>
</div>

{{-- Quick action panels --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">

    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}"
       class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div class="font-semibold text-gray-900">مدیریت کلیدهای SSH</div>
        </div>
        <p class="text-xs text-gray-500">تولید Ed25519/RSA-4096، نصب، چرخش و ابطال کلید</p>
    </a>

    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.api-tokens') }}"
       class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:border-orange-300 hover:shadow-md transition-all group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="font-semibold text-gray-900">توکن‌های API</div>
        </div>
        <p class="text-xs text-gray-500">مدیریت توکن cPanel/DirectAdmin، وضعیت و آخرین استفاده</p>
    </a>

    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.security-audit') }}"
       class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:border-red-300 hover:shadow-md transition-all group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition-colors">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="font-semibold text-gray-900">حسابرسی امنیتی</div>
        </div>
        <p class="text-xs text-gray-500">بررسی روش‌های احراز هویت، Fingerprint و هشدارهای امنیتی</p>
    </a>

</div>

{{-- Password-only warning --}}
@if($passwordOnly > 0)
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 flex gap-3">
    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    <div>
        <p class="text-red-800 font-semibold text-sm">{{ $passwordOnly }} مقصد فقط از رمز عبور استفاده می‌کند</p>
        <p class="text-red-700 text-xs mt-1">استفاده از کلید SSH امن‌تر و مطمئن‌تر است. از ویزارد انتقال برای ارتقا استفاده کنید.</p>
    </div>
</div>
@endif

{{-- Recent SSH Keys --}}
@if($recentKeys->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-800">کلیدهای SSH اخیر</h2>
        <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys') }}" class="text-xs text-indigo-600 hover:underline">همه</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">نام</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">نوع</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Fingerprint</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 hidden sm:table-cell">ایجاد</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($recentKeys as $key)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2.5">
                    <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $key) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $key->label }}</a>
                </td>
                <td class="px-4 py-2.5 text-xs text-gray-500">{{ $key->keyTypeLabel() }}</td>
                <td class="px-4 py-2.5">
                    @if($key->status === 'active')
                        <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full">فعال</span>
                    @elseif($key->status === 'pending')
                        <span class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">در انتظار نصب</span>
                    @elseif($key->status === 'rotated')
                        <span class="text-xs bg-gray-100 text-gray-500 border border-gray-200 px-2 py-0.5 rounded-full">چرخیده</span>
                    @else
                        <span class="text-xs bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded-full">ابطال‌شده</span>
                    @endif
                </td>
                <td class="px-4 py-2.5 font-mono text-xs text-gray-400">{{ $key->shortFingerprint() }}</td>
                <td class="px-4 py-2.5 text-xs text-gray-400 hidden sm:table-cell">{{ $key->created_at->diffForHumans() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
