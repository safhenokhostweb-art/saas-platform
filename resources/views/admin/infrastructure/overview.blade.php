@extends('layouts.admin')

@section('header', 'مدیریت زیرساخت')

@push('head')
<style>
.infra-stat{position:relative;overflow:hidden;border-radius:1rem;padding:1.25rem 1.5rem;color:#fff;text-decoration:none;display:block;transition:all .3s;border:1px solid rgba(255,255,255,0.12)}
.infra-stat:hover{transform:translateY(-4px);box-shadow:0 20px 48px rgba(15,23,42,0.2)}
.infra-stat--blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}
.infra-stat--green{background:linear-gradient(135deg,#059669,#064e3b)}
.infra-stat--red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.infra-stat--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.infra-stat--violet{background:linear-gradient(135deg,#7c3aed,#2e1065)}
.infra-stat--sky{background:linear-gradient(135deg,#0284c7,#082f49)}
.infra-stat__label{font-size:.8rem;font-weight:500;opacity:.85;margin-bottom:.4rem}
.infra-stat__value{font-size:2rem;font-weight:800;line-height:1}
.infra-stat__sub{font-size:.75rem;opacity:.7;margin-top:.4rem}
.infra-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,0.05);padding:1.25rem 1.5rem}
.infra-section-btn{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;border-radius:.875rem;border:1px solid #e2e8f0;background:#fff;text-decoration:none;color:#374151;transition:all .2s;box-shadow:0 1px 4px rgba(15,23,42,0.04)}
.infra-section-btn:hover{border-color:#6366f1;background:#f5f3ff;color:#4f46e5;transform:translateX(-2px);box-shadow:0 4px 16px rgba(99,102,241,0.15)}
.infra-section-btn__icon{width:2.75rem;height:2.75rem;border-radius:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.alert-row{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-radius:.75rem;margin-bottom:.5rem;font-size:.875rem}
.alert-row--red{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alert-row--amber{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
.alert-row--blue{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">زیرساخت پلتفرم</h1>
        <p class="text-gray-500 text-sm mt-1">نمای کلی از سرورها، دامنه‌ها، هاستینگ، استوریج و دیتابیس‌ها</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.infrastructure.servers.create') }}" class="btn btn-primary text-sm">
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            افزودن سرور
        </a>
    </div>
</div>

{{-- Alert Banner --}}
@if($expiringDomains > 0 || $expiredDomains > 0 || $sslExpiring > 0 || $offlineServers > 0)
<div class="infra-card mb-6">
    <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        هشدارها و موارد نیازمند توجه
    </h3>
    @if($offlineServers > 0)
    <div class="alert-row alert-row--red">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ $offlineServers }} سرور آفلاین شناسایی شد</span>
        <a href="{{ route('admin.infrastructure.servers.index', ['status' => 'offline']) }}" class="mr-auto text-xs underline">مشاهده</a>
    </div>
    @endif
    @if($expiredDomains > 0)
    <div class="alert-row alert-row--red">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
        <span>{{ $expiredDomains }} دامنه منقضی شده است</span>
        <a href="{{ route('admin.infrastructure.domains.index') }}" class="mr-auto text-xs underline">مشاهده</a>
    </div>
    @endif
    @if($expiringDomains > 0)
    <div class="alert-row alert-row--amber">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ $expiringDomains }} دامنه در ۳۰ روز آینده منقضی می‌شود</span>
        <a href="{{ route('admin.infrastructure.domains.index', ['expiring' => 1]) }}" class="mr-auto text-xs underline">مشاهده</a>
    </div>
    @endif
    @if($sslExpiring > 0)
    <div class="alert-row alert-row--amber">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <span>{{ $sslExpiring }} گواهی SSL در ۱۴ روز آینده منقضی می‌شود</span>
        <a href="{{ route('admin.infrastructure.domains.index', ['ssl_expiring' => 1]) }}" class="mr-auto text-xs underline">مشاهده</a>
    </div>
    @endif
</div>
@endif

{{-- Stats Grid --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <a href="{{ route('admin.infrastructure.servers.index') }}" class="infra-stat infra-stat--blue">
        <div class="infra-stat__label">سرورها</div>
        <div class="infra-stat__value">{{ \App\Helpers\NumberHelper::formatFa($servers) }}</div>
        <div class="infra-stat__sub">{{ \App\Helpers\NumberHelper::formatFa($onlineServers) }} آنلاین</div>
    </a>
    <a href="{{ route('admin.infrastructure.domains.index') }}" class="infra-stat infra-stat--violet">
        <div class="infra-stat__label">دامنه‌ها</div>
        <div class="infra-stat__value">{{ \App\Helpers\NumberHelper::formatFa($domains) }}</div>
        <div class="infra-stat__sub">{{ \App\Helpers\NumberHelper::formatFa($expiringDomains) }} رو به انقضا</div>
    </a>
    <a href="{{ route('admin.infrastructure.domains.index', ['ssl_expiring' => 1]) }}" class="infra-stat {{ $sslExpiring > 0 ? 'infra-stat--amber' : 'infra-stat--green' }}">
        <div class="infra-stat__label">SSL</div>
        <div class="infra-stat__value">{{ \App\Helpers\NumberHelper::formatFa($sslExpiring) }}</div>
        <div class="infra-stat__sub">رو به انقضا</div>
    </a>
    <a href="{{ route('admin.infrastructure.hosting.index') }}" class="infra-stat infra-stat--sky">
        <div class="infra-stat__label">هاستینگ</div>
        <div class="infra-stat__value">—</div>
        <div class="infra-stat__sub">مشاهده جزئیات</div>
    </a>
    <a href="{{ route('admin.infrastructure.storage.index') }}" class="infra-stat infra-stat--green">
        <div class="infra-stat__label">استوریج</div>
        <div class="infra-stat__value">{{ \App\Helpers\NumberHelper::formatFa($storageProviders) }}</div>
        <div class="infra-stat__sub">سرویس فعال</div>
    </a>
    <a href="{{ route('admin.infrastructure.databases.index') }}" class="infra-stat infra-stat--red">
        <div class="infra-stat__label">دیتابیس</div>
        <div class="infra-stat__value">{{ \App\Helpers\NumberHelper::formatFa($dbServers) }}</div>
        <div class="infra-stat__sub">سرور فعال</div>
    </a>
</div>

{{-- Navigation Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

    <a href="{{ route('admin.infrastructure.servers.index') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon bg-blue-50">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">مدیریت سرورها</div>
            <div class="text-xs text-gray-500 mt-0.5">نقش، IP، وضعیت، منابع</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <a href="{{ route('admin.infrastructure.domains.index') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon bg-violet-50">
            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">مدیریت دامنه‌ها</div>
            <div class="text-xs text-gray-500 mt-0.5">SSL، ثبت‌کننده، انقضا، DNS</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <a href="{{ route('admin.infrastructure.hosting.index') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon bg-sky-50">
            <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">مدیریت هاستینگ</div>
            <div class="text-xs text-gray-500 mt-0.5">پروایدر، تجدید، منابع</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <a href="{{ route('admin.infrastructure.storage.index') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon bg-emerald-50">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">مدیریت استوریج</div>
            <div class="text-xs text-gray-500 mt-0.5">ظرفیت، مصرف، رشد</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <a href="{{ route('admin.infrastructure.databases.index') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon bg-rose-50">
            <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">مدیریت دیتابیس</div>
            <div class="text-xs text-gray-500 mt-0.5">نسخه، اندازه، پشتیبان‌گیری</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <a href="{{ route('admin.infrastructure.sms') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon bg-sky-50">
            <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">آمار پیامک</div>
            <div class="text-xs text-gray-500 mt-0.5">نرخ موفقیت، failover، لاگ‌ها</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <a href="{{ route('admin.infrastructure.remote-agents.index') }}" class="infra-section-btn">
        <div class="infra-section-btn__icon" style="background:#f5f3ff">
            <svg class="w-6 h-6" style="color:#7c3aed" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <div class="font-semibold text-sm">Remote Agents</div>
            <div class="text-xs text-gray-500 mt-0.5">اجرای command روی سرورهای مشتری</div>
        </div>
        <svg class="w-4 h-4 text-gray-400 mr-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>

</div>

{{-- Recent Servers + Critical Domains --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <div class="infra-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800">آخرین سرورها</h3>
            <a href="{{ route('admin.infrastructure.servers.index') }}" class="text-xs text-indigo-600 hover:underline">همه سرورها</a>
        </div>
        @forelse($recentServers as $server)
        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full {{ $server->status === 'online' ? 'bg-green-500' : ($server->status === 'offline' ? 'bg-red-500' : 'bg-yellow-500') }}"></span>
                <div>
                    <div class="text-sm font-medium text-gray-800">{{ $server->name }}</div>
                    <div class="text-xs text-gray-400">{{ $server->ip_address ?? '—' }}</div>
                </div>
            </div>
            <div class="text-xs text-gray-500">{{ $server->roles_label ?: '—' }}</div>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-4">هنوز سروری ثبت نشده است.</p>
        @endforelse
    </div>

    <div class="infra-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800">دامنه‌های در معرض خطر</h3>
            <a href="{{ route('admin.infrastructure.domains.index', ['expiring' => 1]) }}" class="text-xs text-indigo-600 hover:underline">همه</a>
        </div>
        @forelse($criticalDomains as $domain)
        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
            <div class="text-sm font-medium text-gray-800">{{ $domain->domain }}</div>
            <div class="text-xs {{ $domain->days_until_expiry < 0 ? 'text-red-600 font-bold' : ($domain->days_until_expiry <= 7 ? 'text-red-500' : 'text-amber-600') }}">
                @if($domain->days_until_expiry < 0)
                    منقضی شده
                @else
                    {{ \App\Helpers\NumberHelper::formatFa($domain->days_until_expiry) }} روز مانده
                @endif
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-4">هیچ دامنه‌ای در معرض انقضا نیست.</p>
        @endforelse
    </div>

</div>

@endsection
