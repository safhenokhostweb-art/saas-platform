@extends('layouts.admin')

@section('title', 'پذیرش ماژول‌ها')

@push('head')
<style>
.ma-stat{position:relative;overflow:hidden;border-radius:1rem;padding:1.25rem 1.5rem;background:#fff;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,0.05);transition:all .25s}
.ma-stat:hover{box-shadow:0 8px 24px rgba(15,23,42,0.1);transform:translateY(-2px)}
.ma-stat__icon{width:3rem;height:3rem;border-radius:.875rem;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;flex-shrink:0}
.ma-stat__value{font-size:2rem;font-weight:800;line-height:1;color:#0f172a}
.ma-stat__label{font-size:.8rem;font-weight:500;color:#64748b;margin-top:.25rem}
.ma-stat__bar-wrap{margin-top:.875rem;background:#f1f5f9;border-radius:9999px;height:6px;overflow:hidden}
.ma-stat__bar{height:100%;border-radius:9999px;transition:width .8s ease}
.ma-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,0.05);padding:1.25rem 1.5rem}
.ma-slug-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:9999px;font-size:.7rem;font-weight:600;letter-spacing:.03em}
.ma-slug-badge--crm{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.ma-slug-badge--inventory{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.ma-slug-badge--courses{background:#fdf4ff;color:#7e22ce;border:1px solid #e9d5ff}
.ma-slug-badge--subscription{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.ma-slug-badge--forms{background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd}
.ma-slug-badge--tickets{background:#fef9c3;color:#854d0e;border:1px solid #fde047}
.site-row{display:flex;align-items:center;gap:.75rem;padding:.625rem 0;border-bottom:1px solid #f1f5f9}
.site-row:last-child{border-bottom:0}
.site-row__avatar{width:2.25rem;height:2.25rem;border-radius:.625rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.75rem;font-weight:700;color:#fff}
.act-row{display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid #f8fafc;font-size:.8125rem}
.act-row:last-child{border-bottom:0}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">پذیرش ماژول‌ها</h1>
        <p class="text-gray-500 text-sm mt-1">نمای کلی از فعال‌سازی ماژول‌ها در سایت‌های مشتری</p>
    </div>
    <a href="{{ route('admin.sites.index') }}" class="btn btn-secondary btn-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        مدیریت سایت‌ها
    </a>
</div>

{{-- Summary banner --}}
<div class="ma-card mb-6 flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
    </div>
    <div>
        <p class="font-bold text-gray-800 text-sm">پلتفرم شما <span class="text-indigo-600">{{ $totalSites }}</span> سایت فعال دارد</p>
        <p class="text-xs text-gray-500 mt-0.5">درصد پذیرش هر ماژول نسبت به این تعداد محاسبه شده است</p>
    </div>
</div>

{{-- Module Adoption Grid --}}
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
    @php
    $colors = [
        'crm'          => ['bg' => '#eff6ff', 'bar' => '#3b82f6', 'icon' => 'text-blue-600', 'icon_bg' => 'bg-blue-50'],
        'inventory'    => ['bg' => '#f0fdf4', 'bar' => '#22c55e', 'icon' => 'text-emerald-600', 'icon_bg' => 'bg-emerald-50'],
        'courses'      => ['bg' => '#fdf4ff', 'bar' => '#a855f7', 'icon' => 'text-purple-600', 'icon_bg' => 'bg-purple-50'],
        'subscription' => ['bg' => '#fff7ed', 'bar' => '#f97316', 'icon' => 'text-orange-600', 'icon_bg' => 'bg-orange-50'],
        'forms'        => ['bg' => '#f0f9ff', 'bar' => '#0ea5e9', 'icon' => 'text-sky-600', 'icon_bg' => 'bg-sky-50'],
        'tickets'      => ['bg' => '#fefce8', 'bar' => '#eab308', 'icon' => 'text-yellow-600', 'icon_bg' => 'bg-yellow-50'],
    ];
    $icons = [
        'crm'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'inventory'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
        'courses'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'subscription' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>',
        'forms'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
        'tickets'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
    ];
    $labels = ['crm'=>'CRM مخاطبین','inventory'=>'انبارداری','courses'=>'آموزش','subscription'=>'اشتراک','forms'=>'فرم‌ها','tickets'=>'تیکت‌ها'];
    @endphp
    @foreach($adoption as $slug => $data)
    @php $c = $colors[$slug] ?? ['icon'=>'text-indigo-600','icon_bg'=>'bg-indigo-50','bar'=>'#6366f1']; @endphp
    <div class="ma-stat">
        <div class="ma-stat__icon {{ $c['icon_bg'] }}">
            <svg class="w-6 h-6 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $icons[$slug] ?? '' !!}
            </svg>
        </div>
        <p class="ma-stat__value">{{ $data['active_count'] }}</p>
        <p class="ma-stat__label">{{ $labels[$slug] ?? $data['module']->title }}</p>
        <div class="ma-stat__bar-wrap">
            <div class="ma-stat__bar" style="width:{{ $data['percentage'] }}%; background:{{ $c['bar'] }}"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1.5">{{ $data['percentage'] }}٪ از سایت‌های فعال</p>
    </div>
    @endforeach
</div>

{{-- Two columns: CRM sites + Inventory sites --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    {{-- CRM Sites --}}
    <div class="ma-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-gray-800 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                سایت‌های دارای CRM
            </h2>
            <span class="text-sm font-bold text-blue-600">{{ $crmSites->count() }} سایت</span>
        </div>
        @if($crmSites->isEmpty())
        <div class="text-center py-8 text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            <p class="text-sm">هنوز هیچ سایتی ماژول CRM ندارد</p>
        </div>
        @else
        <div>
            @foreach($crmSites as $site)
            <div class="site-row">
                <div class="site-row__avatar">{{ mb_substr($site->title ?? '؟', 0, 1) }}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $site->title }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ $site->domain }}</p>
                </div>
                <a href="{{ route('admin.sites.modules.edit', $site) }}"
                   class="text-xs text-indigo-600 hover:underline whitespace-nowrap flex-shrink-0">مدیریت</a>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Inventory Sites --}}
    <div class="ma-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-gray-800 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </span>
                سایت‌های دارای انبارداری
            </h2>
            <span class="text-sm font-bold text-emerald-600">{{ $inventorySites->count() }} سایت</span>
        </div>
        @if($inventorySites->isEmpty())
        <div class="text-center py-8 text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            <p class="text-sm">هنوز هیچ سایتی ماژول انبارداری ندارد</p>
        </div>
        @else
        <div>
            @foreach($inventorySites as $site)
            <div class="site-row">
                <div class="site-row__avatar" style="background:linear-gradient(135deg,#059669,#10b981)">{{ mb_substr($site->title ?? '؟', 0, 1) }}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $site->title }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ $site->domain }}</p>
                </div>
                <a href="{{ route('admin.sites.modules.edit', $site) }}"
                   class="text-xs text-indigo-600 hover:underline whitespace-nowrap flex-shrink-0">مدیریت</a>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>

{{-- Recent activations --}}
<div class="ma-card">
    <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg bg-violet-50 flex items-center justify-center">
            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        آخرین فعال‌سازی‌ها — ۳۰ روز گذشته
    </h2>
    @if($recentActivations->isEmpty())
    <p class="text-center text-gray-400 py-6 text-sm">فعال‌سازی جدیدی در این بازه وجود ندارد</p>
    @else
    <div>
        @foreach($recentActivations as $act)
        <div class="act-row">
            <span class="ma-slug-badge ma-slug-badge--{{ $act->slug }}">{{ $labels[$act->slug] ?? $act->module_title }}</span>
            <span class="font-medium text-gray-800">{{ $act->site_title }}</span>
            <span class="text-gray-400">·</span>
            <span class="text-gray-500 text-xs">{{ $act->domain }}</span>
            <span class="mr-auto text-gray-400 text-xs">{{ \App\Helpers\Jalali::diffForHumansFa(\Carbon\Carbon::parse($act->activated_at)) }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>

@endsection
