@extends('admin.layouts.app')

@section('title', 'مرکز چرخه عمر سایت‌ها')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">چرخه عمر سایت‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">مدیریت وضعیت سایت‌های مشتریان بر اساس اشتراک و پرداخت</p>
        </div>
        <a href="{{ route('admin.lifecycle.settings') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            تنظیمات
        </a>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @php
        $cards = [
            ['label' => 'فعال',          'key' => 'active',       'color' => 'green',  'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'دوره اعتبار',   'key' => 'grace_period', 'color' => 'yellow', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'معلق',          'key' => 'suspended',    'color' => 'red',    'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
            ['label' => 'آرشیو',         'key' => 'archived',     'color' => 'gray',   'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
            ['label' => 'حذف‌شده',       'key' => 'deleted',      'color' => 'slate',  'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
        ];
        $colorMap = [
            'green'  => 'bg-green-50 border-green-200 text-green-700',
            'yellow' => 'bg-yellow-50 border-yellow-200 text-yellow-700',
            'red'    => 'bg-red-50 border-red-200 text-red-700',
            'gray'   => 'bg-gray-50 border-gray-200 text-gray-700',
            'slate'  => 'bg-slate-100 border-slate-300 text-slate-700',
        ];
        @endphp

        @foreach($cards as $card)
        <div class="border rounded-xl p-4 {{ $colorMap[$card['color']] }} text-center">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $card['icon'] }}"/>
            </svg>
            <div class="text-3xl font-bold">{{ $summary[$card['key']] }}</div>
            <div class="text-xs mt-1 font-medium">{{ $card['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Grace Period Sites --}}
    @if($gracePeriodSites->isNotEmpty())
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-yellow-200 flex items-center gap-2">
            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="font-bold text-yellow-800">سایت‌های در دوره اعتبار ({{ $gracePeriodSites->count() }})</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-yellow-100 text-yellow-800 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-right">سایت</th>
                    <th class="px-4 py-2 text-right">مشتری</th>
                    <th class="px-4 py-2 text-right">پایان دوره اعتبار</th>
                    <th class="px-4 py-2 text-right">روزهای باقی‌مانده</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-yellow-100">
                @foreach($gracePeriodSites as $site)
                <tr class="hover:bg-yellow-100/50 transition">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $site->title }}</div>
                        <div class="text-xs text-gray-500">{{ $site->domain }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $site->customer?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">
                        {{ $site->grace_period_ends_at ? \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $site->grace_period_ends_at) : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @php $days = $site->gracePeriodDaysRemaining(); @endphp
                        <span class="font-bold {{ $days <= 3 ? 'text-red-600' : 'text-yellow-700' }}">
                            {{ $days ?? '—' }} روز
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.sites.lifecycle.index', $site) }}"
                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                            مدیریت
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Suspended Sites --}}
    @if($suspendedSites->isNotEmpty())
    <div class="bg-red-50 border border-red-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-red-200 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            <h2 class="font-bold text-red-800">سایت‌های معلق ({{ $suspendedSites->count() }})</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-red-100 text-red-800 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-right">سایت</th>
                    <th class="px-4 py-2 text-right">مشتری</th>
                    <th class="px-4 py-2 text-right">تاریخ تعلیق</th>
                    <th class="px-4 py-2 text-right">روزهای معلق</th>
                    <th class="px-4 py-2 text-right">دلیل</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-red-100">
                @foreach($suspendedSites as $site)
                <tr class="hover:bg-red-100/50 transition">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $site->title }}</div>
                        <div class="text-xs text-gray-500">{{ $site->domain }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $site->customer?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">
                        {{ $site->suspended_at ? \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $site->suspended_at) : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-bold text-red-700">{{ $site->daysSinceSuspension() ?? '—' }} روز</span>
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs max-w-xs truncate">
                        {{ $site->suspended_reason ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.sites.lifecycle.index', $site) }}"
                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                            مدیریت
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Archived Sites --}}
    @if($archivedSites->isNotEmpty())
    <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
            <h2 class="font-bold text-gray-700">سایت‌های آرشیو‌شده ({{ $archivedSites->count() }})</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-right">سایت</th>
                    <th class="px-4 py-2 text-right">مشتری</th>
                    <th class="px-4 py-2 text-right">تاریخ آرشیو</th>
                    <th class="px-4 py-2 text-right">روزهای آرشیو</th>
                    <th class="px-4 py-2 text-right">درخواست بازگردانی</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($archivedSites as $site)
                <tr class="hover:bg-gray-100/60 transition">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $site->title }}</div>
                        <div class="text-xs text-gray-500">{{ $site->domain }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $site->customer?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $site->archived_at ? \Morilog\Jalali\CalendarUtils::strftime('Y/m/d', $site->archived_at) : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-700">{{ $site->daysSinceArchival() ?? '—' }} روز</span>
                    </td>
                    <td class="px-4 py-3">
                        @if($site->restore_requested_at)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs">
                            درخواست داده
                        </span>
                        @else
                        <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.sites.lifecycle.index', $site) }}"
                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                            مدیریت
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($gracePeriodSites->isEmpty() && $suspendedSites->isEmpty() && $archivedSites->isEmpty())
    <div class="bg-green-50 border border-green-200 rounded-xl p-8 text-center">
        <svg class="w-12 h-12 text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-green-700 font-medium">تمام سایت‌ها در وضعیت فعال هستند.</p>
    </div>
    @endif

</div>
@endsection
