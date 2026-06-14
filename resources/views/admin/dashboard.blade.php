@extends('layouts.admin')

@section('header', 'داشبورد')

@section('content')

@php
    $modulesCount        = $modulesCount ?? 0;
    $activeLicensesCount = $activeLicensesCount ?? 0;
@endphp

@push('head')
<style>
.glass-card{background:rgba(255,255,255,.72);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.25);box-shadow:0 4px 16px rgba(15,23,42,.06)}
.glass-card-strong{background:rgba(255,255,255,.85);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.4);box-shadow:0 4px 16px rgba(15,23,42,.07)}
.glass-table thead th{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.5rem .75rem;background:rgba(99,102,241,.05);border-bottom:2px solid rgba(99,102,241,.12);white-space:nowrap}
.glass-table tbody td{padding:.45rem .75rem;border-bottom:1px solid rgba(226,232,240,.4);font-size:.8rem}
.glass-table tbody tr:hover{background:rgba(99,102,241,.03)}
.badge-glass{display:inline-flex;align-items:center;padding:.2rem .6rem;border-radius:9999px;font-size:.7rem;font-weight:600;gap:.3rem}
.badge-glass--success{background:rgba(16,185,129,.15);color:#059669}
.badge-glass--danger{background:rgba(239,68,68,.15);color:#dc2626}
.stat-tile{position:relative;display:flex;flex-direction:column;overflow:hidden;border-radius:.75rem;padding:.875rem 1rem;box-shadow:0 4px 12px rgba(15,23,42,.1);transition:all .25s;color:#fff!important;text-decoration:none;border:1px solid rgba(255,255,255,.15)}
.stat-tile:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(15,23,42,.15)}
.stat-tile--violet{background:linear-gradient(135deg,#7c3aed,#1e1b4b)}
.stat-tile--sky{background:linear-gradient(135deg,#0284c7,#0f172a)}
.stat-tile--emerald{background:linear-gradient(135deg,#059669,#022c22)}
.stat-tile--rose{background:linear-gradient(135deg,#e11d48,#4c0519)}
.stat-tile--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.stat-tile--indigo{background:linear-gradient(135deg,#6366f1,#1e1b4b)}
.stat-tile__value{font-size:1.5rem;font-weight:800;line-height:1;margin:.25rem 0 .15rem}
.stat-tile__label{font-size:.7rem;opacity:.85;font-weight:500}
.stat-tile__hint{font-size:.65rem;opacity:.7}
.pulse-dot{display:inline-block;width:7px;height:7px;border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.2)}}
.scrollbar-thin::-webkit-scrollbar{width:4px;height:4px}
.scrollbar-thin::-webkit-scrollbar-thumb{background:rgba(148,163,184,.3);border-radius:8px}
details>summary{list-style:none;cursor:pointer}
details>summary::-webkit-details-marker{display:none}
details[open]>summary .chevron{transform:rotate(180deg)}
.chevron{transition:transform .2s;display:inline-block}
</style>
@endpush

{{-- ── Header compact ── --}}
<div class="flex items-center justify-between mb-4 px-1">
    <div>
        <h2 class="text-xl font-bold text-gray-900">خوش آمدید، <span class="bg-gradient-to-l from-indigo-600 to-purple-600 bg-clip-text text-transparent">{{ auth()->user()->name ?? 'مدیر' }}</span></h2>
        <p class="text-gray-400 text-xs mt-0.5">آخرین وضعیت پلتفرم</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1.5">
        <span class="pulse-dot bg-green-500"></span> سیستم فعال
    </div>
</div>

{{-- ── Stat tiles ── --}}
<div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-4">
    <a href="{{ route('admin.customers.index') }}" class="stat-tile stat-tile--violet">
        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($customers) }}</div>
        <div class="stat-tile__label">مشتری‌ها</div>
    </a>
    <a href="{{ route('admin.sites.index') }}" class="stat-tile stat-tile--sky">
        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($sites) }}</div>
        <div class="stat-tile__label">سایت‌ها</div>
    </a>
    <a href="{{ route('admin.forms.index') }}" class="stat-tile stat-tile--emerald">
        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($forms) }}</div>
        <div class="stat-tile__label">فرم‌ها</div>
    </a>
    <a href="{{ route('admin.modules.index') }}" class="stat-tile stat-tile--rose">
        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($modulesCount) }}</div>
        <div class="stat-tile__label">ماژول‌ها</div>
    </a>
    <a href="{{ route('admin.licenses.index') }}" class="stat-tile stat-tile--amber">
        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($activeLicensesCount) }}</div>
        <div class="stat-tile__label">لایسنس فعال</div>
    </a>
</div>

{{-- ── Quick links ── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
    <a href="{{ route('admin.platform-settings.index') }}" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm hover:border-indigo-300 hover:shadow transition text-sm">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 text-base">⚙</span>
        <span class="font-medium text-slate-700">تنظیمات</span>
    </a>
    <a href="{{ route('admin.updates.index') }}" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm hover:border-sky-300 hover:shadow transition text-sm">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-700 text-base">↻</span>
        <span class="font-medium text-slate-700">بروزرسانی</span>
    </a>
    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm hover:border-violet-300 hover:shadow transition text-sm">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-100 text-violet-700 text-base">👤</span>
        <span class="font-medium text-slate-700">کاربران</span>
    </a>
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm hover:border-slate-300 hover:shadow transition text-sm">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 text-base">⌂</span>
        <span class="font-medium text-slate-700">داشبورد کاربر</span>
    </a>
</div>

{{-- ── Two-column middle zone ── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    {{-- Chart --}}
    <div class="glass-card-strong rounded-xl p-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 text-base">📊</span>
            <span class="font-semibold text-gray-800 text-sm">آمار کلی</span>
        </div>
        <div style="height:180px">
            <canvas id="statsChart"></canvas>
        </div>
    </div>

    {{-- Expiry reminders --}}
    @if(isset($expiryReminders) && $expiryReminders->isNotEmpty())
    <div class="glass-card-strong rounded-xl p-4 border-r-4 border-r-amber-400">
        <div class="flex items-center gap-2 mb-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 text-base">⚠</span>
            <span class="font-semibold text-gray-800 text-sm">هشدارهای انقضا</span>
            <span class="ms-auto text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">{{ $expiryReminders->count() }}</span>
        </div>
        <ul class="space-y-1.5 max-h-40 overflow-y-auto scrollbar-thin">
            @foreach($expiryReminders as $reminder)
            <li class="flex items-start gap-2 p-2 rounded-lg bg-amber-50 border border-amber-100">
                <span class="shrink-0 w-1.5 h-1.5 mt-1.5 rounded-full {{ $reminder->type === 'support' ? 'bg-blue-500' : ($reminder->type === 'host' ? 'bg-purple-500' : 'bg-teal-500') }}"></span>
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-medium text-gray-800">{{ \App\Helpers\NumberHelper::toPersianNumbers(Str::limit($reminder->title ?? 'هشدار انقضا', 40)) }}</span>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $reminder->sent_at ? \App\Helpers\Jalali::formatFa($reminder->sent_at, 'Y/m/d') : '' }}</p>
                </div>
                @if($reminder->site)
                <a href="{{ route('admin.sites.edit', $reminder->site) }}" class="shrink-0 text-xs text-indigo-600 hover:underline">ویرایش</a>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
    @else
    {{-- Guide (fallback when no expiry warnings) --}}
    <div class="glass-card-strong rounded-xl p-4">
        @include('admin.dashboard.guide')
    </div>
    @endif

</div>

{{-- ── Collapsible: Forms × Sites ── --}}
@if(isset($formSiteColumnExists))
@php
    $formsList = $formsList ?? collect();
    $sitesList = $sitesList ?? collect();
@endphp
<details class="glass-card-strong rounded-xl mb-3 overflow-hidden">
    <summary class="flex items-center gap-2 px-4 py-3 hover:bg-indigo-50/30 transition">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 text-base">📋</span>
        <span class="font-semibold text-gray-800 text-sm flex-1">فرم‌ها در سایت‌های مشتری</span>
        <span class="text-xs text-gray-400">{{ $formsList->count() }} فرم — {{ $sitesList->count() }} سایت</span>
        <svg class="chevron w-4 h-4 text-gray-400 ms-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </summary>
    <div class="px-4 pb-4">
        @if(!$formSiteColumnExists)
            <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm">ستون <code>is_active</code> در جدول <code>form_site</code> یافت نشد. <code class="bg-gray-100 px-1 rounded">php artisan migrate</code></div>
        @else
            <div class="overflow-x-auto scrollbar-thin rounded-lg border border-gray-100 max-h-64 overflow-y-auto">
                <table class="w-full text-right border-collapse glass-table">
                    <thead>
                        <tr>
                            <th>فرم</th>
                            @foreach($sitesList as $site)
                                <th>{{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }}</th>
                            @endforeach
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($formsList as $form)
                        <tr>
                            <td>{{ \App\Helpers\NumberHelper::toPersianNumbers($form->title) }}</td>
                            @foreach($sitesList as $site)
                                @php
                                    $pivot = $form->sites->firstWhere('id', $site->id);
                                    $isActive = $pivot ? (bool) ($pivot->pivot->is_active ?? true) : false;
                                @endphp
                                <td>
                                    <form action="{{ route('admin.forms.sites.toggle', [$form, $site]) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium {{ $isActive ? 'badge-glass badge-glass--success' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                            {{ $isActive ? '✓ فعال' : '— غیرفعال' }}
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap">
                                <a href="{{ route('admin.forms.sites.edit', $form) }}" class="text-indigo-600 hover:underline text-xs">سایت‌ها</a>
                                <span class="mx-1 text-gray-400">|</span>
                                <a href="{{ route('admin.forms.edit', $form) }}" class="text-indigo-600 hover:underline text-xs">ویرایش</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ count($sitesList) + 2 }}" class="text-center text-gray-400 py-6 text-xs">هنوز فرمی تعریف نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</details>
@endif

{{-- ── Collapsible: Sites × Modules ── --}}
@if(isset($sitesWithModules) && isset($allModules))
<details class="glass-card-strong rounded-xl mb-3 overflow-hidden">
    <summary class="flex items-center gap-2 px-4 py-3 hover:bg-indigo-50/30 transition">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 text-rose-700 text-base">🧩</span>
        <span class="font-semibold text-gray-800 text-sm flex-1">ماژول‌های سایت‌های مشتری</span>
        <span class="text-xs text-gray-400">{{ $sitesWithModules->count() }} سایت — {{ $allModules->count() }} ماژول</span>
        <svg class="chevron w-4 h-4 text-gray-400 ms-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </summary>
    <div class="px-4 pb-4">
        @if($allModules->isEmpty())
            <p class="text-gray-400 text-xs py-4 text-center">هنوز ماژولی تعریف نشده است.</p>
        @else
            <div class="overflow-x-auto scrollbar-thin rounded-lg border border-gray-100 max-h-64 overflow-y-auto">
                <table class="w-full text-right border-collapse glass-table">
                    <thead>
                        <tr>
                            <th>سایت</th>
                            <th>دامنه</th>
                            @foreach($allModules as $mod)
                                <th title="{{ $mod->title ?? $mod->slug }}">{{ $mod->slug }}</th>
                            @endforeach
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sitesWithModules as $site)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('admin.sites.show', $site) }}" class="text-indigo-600 hover:underline">{{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }}</a>
                                <a href="{{ route('admin.sites.modules.edit', $site) }}" class="text-xs text-gray-400 hover:underline ms-1">ماژول‌ها</a>
                            </td>
                            <td class="text-gray-500 text-xs">{{ \App\Helpers\NumberHelper::toPersianNumbers($site->domain ?? '—') }}</td>
                            @foreach($allModules as $mod)
                                @php $attached = $site->modules->contains('id', $mod->id); @endphp
                                <td class="text-center">
                                    @if($attached)
                                        <span class="badge-glass badge-glass--success">✓</span>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <span class="text-xs {{ $site->is_active ? 'text-green-600' : 'text-red-500' }}">{{ $site->is_active ? 'فعال' : 'غیرفعال' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ $allModules->count() + 3 }}" class="text-center text-gray-400 py-6 text-xs">هنوز سایتی ثبت نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</details>
@endif

{{-- ── Guide (when expiry reminders exist, show guide here) ── --}}
@if(isset($expiryReminders) && $expiryReminders->isNotEmpty())
<details class="glass-card-strong rounded-xl mb-3 overflow-hidden">
    <summary class="flex items-center gap-2 px-4 py-3 hover:bg-indigo-50/30 transition">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-700 text-base">📖</span>
        <span class="font-semibold text-gray-800 text-sm flex-1">راهنمای داشبورد</span>
        <svg class="chevron w-4 h-4 text-gray-400 ms-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </summary>
    <div class="px-4 pb-4">
        @include('admin.dashboard.guide')
    </div>
</details>
@endif

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('statsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['مشتری‌ها', 'سایت‌ها', 'فرم‌ها'],
            datasets: [{
                label: 'آمار',
                data: @json([$customers, $sites, $forms]),
                backgroundColor: ['rgba(124,58,237,.7)', 'rgba(2,132,199,.7)', 'rgba(5,150,105,.7)'],
                borderColor:     ['rgba(124,58,237,1)', 'rgba(2,132,199,1)', 'rgba(5,150,105,1)'],
                borderWidth: 2, borderRadius: 6, barPercentage: 0.55
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,.9)', titleColor: '#1e293b',
                    bodyColor: '#475569', borderColor: 'rgba(226,232,240,1)', borderWidth: 1,
                    cornerRadius: 8, rtl: true,
                    titleFont: { family: 'Vazirmatn' }, bodyFont: { family: 'Vazirmatn' }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(226,232,240,.4)' }, ticks: { font: { family: 'Vazirmatn' }, padding: 4 } },
                x: { grid: { display: false }, ticks: { font: { family: 'Vazirmatn' } } }
            }
        }
    });
});
</script>
@endsection
