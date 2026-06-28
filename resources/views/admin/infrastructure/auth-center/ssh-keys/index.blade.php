@extends('layouts.admin')

@section('header', 'کلیدهای SSH')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.index') }}" class="hover:text-indigo-600">مرکز احراز هویت</a>
            <span>/</span>
            <span>کلیدهای SSH</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900">مدیریت کلیدهای SSH</h1>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.import') }}"
           class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            وارد کردن کلید
        </a>
        <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.generate') }}"
           class="btn btn-primary text-sm flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            تولید کلید جدید
        </a>
    </div>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    @if($keys->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            <p class="text-base font-semibold text-gray-500 mb-1">هیچ کلید SSH‌ای ثبت نشده</p>
            <p class="text-sm text-gray-400 mb-5">اولین کلید را تولید یا وارد کنید</p>
            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.generate') }}" class="btn btn-primary text-sm">تولید کلید SSH</a>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">کلید</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Fingerprint</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 hidden lg:table-cell">مقصد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 hidden md:table-cell">نصب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 hidden sm:table-cell">ایجاد</th>
                    <th class="px-4 py-3 w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($keys as $key)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $key) }}" class="font-semibold text-gray-900 hover:text-indigo-600">{{ $key->label }}</a>
                        <div class="text-xs text-gray-400">{{ $key->keyTypeLabel() }}</div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500" title="{{ $key->fingerprint }}">{{ $key->shortFingerprint() }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 hidden lg:table-cell">
                        {{ $key->deploymentTarget?->label ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @if($key->status === 'active')
                            <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full">فعال</span>
                        @elseif($key->status === 'pending')
                            <span class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">در انتظار</span>
                        @elseif($key->status === 'rotated')
                            <span class="text-xs bg-gray-100 text-gray-500 border border-gray-200 px-2 py-0.5 rounded-full">چرخیده</span>
                        @else
                            <span class="text-xs bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded-full">ابطال‌شده</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 hidden md:table-cell">
                        {{ $key->installed_at ? $key->installed_at->diffForHumans() : '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 hidden sm:table-cell">
                        {{ $key->created_at->diffForHumans() }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.show', $key) }}"
                               title="مشاهده" class="p-1.5 text-indigo-500 hover:bg-indigo-50 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            @if($key->status !== 'revoked')
                            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.revoke', $key) }}"
                                  onsubmit="return confirm('ابطال کلید «{{ $key->label }}»؟')">
                                @csrf
                                <button type="submit" title="ابطال" class="p-1.5 text-red-400 hover:bg-red-50 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                            @if($key->status === 'revoked' || $key->status === 'rotated')
                            <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.auth-center.ssh-keys.delete', $key) }}"
                                  onsubmit="return confirm('حذف کامل کلید «{{ $key->label }}»؟')">
                                @csrf @method('DELETE')
                                <button type="submit" title="حذف" class="p-1.5 text-red-400 hover:bg-red-50 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $keys->links() }}</div>
    @endif
</div>

@endsection
