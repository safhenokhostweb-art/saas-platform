@extends('layouts.admin')

@section('header', 'حسابرسی امنیتی')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.index') }}" class="hover:text-indigo-600">مرکز احراز هویت</a>
            <span>/</span>
            <span>حسابرسی امنیتی</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900">حسابرسی امنیتی اعتبارنامه‌ها</h1>
    </div>
</div>

{{-- Findings summary --}}
@php
    $critical = collect($findings)->where('severity', 'CRITICAL')->count();
    $high     = collect($findings)->where('severity', 'HIGH')->count();
    $medium   = collect($findings)->where('severity', 'MEDIUM')->count();
    $low      = collect($findings)->where('severity', 'LOW')->count();
@endphp

<div class="grid grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl border {{ $critical > 0 ? 'border-red-300 bg-red-50' : 'border-gray-200' }} p-4 text-center">
        <div class="text-2xl font-bold {{ $critical > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $critical }}</div>
        <div class="text-xs text-gray-500 mt-0.5">CRITICAL</div>
    </div>
    <div class="bg-white rounded-xl border {{ $high > 0 ? 'border-orange-300' : 'border-gray-200' }} p-4 text-center">
        <div class="text-2xl font-bold {{ $high > 0 ? 'text-orange-600' : 'text-gray-900' }}">{{ $high }}</div>
        <div class="text-xs text-gray-500 mt-0.5">HIGH</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold {{ $medium > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $medium }}</div>
        <div class="text-xs text-gray-500 mt-0.5">MEDIUM</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-bold text-gray-500">{{ $low }}</div>
        <div class="text-xs text-gray-500 mt-0.5">LOW</div>
    </div>
</div>

@if(empty($findings))
    <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
        <svg class="w-12 h-12 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <p class="text-green-800 font-semibold">هیچ یافته‌ای وجود ندارد</p>
        <p class="text-green-700 text-sm mt-1">تمام اعتبارنامه‌ها در وضعیت مطلوب هستند.</p>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">یافته‌ها ({{ count($findings) }})</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($findings as $finding)
            <div class="px-5 py-4 flex items-start gap-4">
                <div class="shrink-0 mt-0.5">
                    @if($finding['severity'] === 'CRITICAL')
                        <span class="inline-block text-xs font-semibold bg-red-600 text-white px-2 py-0.5 rounded">CRITICAL</span>
                    @elseif($finding['severity'] === 'HIGH')
                        <span class="inline-block text-xs font-semibold bg-orange-500 text-white px-2 py-0.5 rounded">HIGH</span>
                    @elseif($finding['severity'] === 'MEDIUM')
                        <span class="inline-block text-xs font-semibold bg-amber-500 text-white px-2 py-0.5 rounded">MEDIUM</span>
                    @else
                        <span class="inline-block text-xs font-semibold bg-gray-400 text-white px-2 py-0.5 rounded">LOW</span>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 text-sm">{{ $finding['server'] }}</div>
                    <div class="text-xs text-gray-600 mt-0.5">{{ $finding['issue'] }}</div>
                </div>
                <div class="shrink-0">
                    @if($finding['action'] === 'migration_wizard')
                        <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.migration-wizard', $finding['target_id']) }}"
                           class="text-xs bg-indigo-50 text-indigo-600 border border-indigo-200 px-2.5 py-1 rounded-lg hover:bg-indigo-100">
                            ویزارد انتقال
                        </a>
                    @elseif($finding['action'] === 'rotate_key')
                        <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $finding['target_id']) }}"
                           class="text-xs bg-amber-50 text-amber-600 border border-amber-200 px-2.5 py-1 rounded-lg hover:bg-amber-100">
                            Rotate کلید
                        </a>
                    @else
                        <a href="{{ route('admin.infrastructure.customer-deployment.servers.edit', $finding['target_id']) }}"
                           class="text-xs bg-gray-50 text-gray-600 border border-gray-200 px-2.5 py-1 rounded-lg hover:bg-gray-100">
                            بررسی
                        </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
@endif

{{-- Auth method breakdown --}}
<div class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">روش‌های احراز هویت — نمای کلی</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">مقصد</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">نوع</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">روش احراز هویت</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Fingerprint</th>
                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">آخرین تست</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($servers as $server)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-medium text-gray-900 text-sm">{{ $server->label }}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-500">{{ strtoupper($server->type) }}</td>
                    <td class="px-4 py-2.5">
                        @if($server->auth_method === 'key')
                            <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full">🔑 کلید SSH</span>
                        @elseif($server->auth_method === 'password')
                            <span class="text-xs bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded-full">🔒 رمز عبور</span>
                        @elseif($server->auth_method === 'api_token')
                            <span class="text-xs bg-orange-50 text-orange-700 border border-orange-200 px-2 py-0.5 rounded-full">🪙 API Token</span>
                        @else
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ $server->auth_method }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">
                        @if($server->key_fingerprint)
                            <span class="text-green-600" title="{{ $server->key_fingerprint }}">✓ ثبت‌شده</span>
                        @else
                            <span class="text-amber-500">تأیید نشده</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-gray-400">
                        @if($server->last_tested_at)
                            {{ $server->last_tested_at->diffForHumans() }}
                            @if($server->last_test_result === 'success')
                                <span class="text-green-600"> (✓)</span>
                            @else
                                <span class="text-red-500"> (✗)</span>
                            @endif
                        @else
                            <span class="text-gray-300">تست نشده</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
