@extends('layouts.admin')

@section('header', 'داشبورد')

@section('content')

@php
    $modulesCount        = $modulesCount ?? 0;
    $activeLicensesCount = $activeLicensesCount ?? 0;
    $revenueThisMonth    = $revenueThisMonth ?? 0;
    $paidInvoicesCount   = $paidInvoicesCount ?? 0;
    $activeExpiryWarnings = $activeExpiryWarnings ?? collect();

    // Chart bar heights (pure CSS – no CDN)
    $chartMax = max($customers, $sites, $forms, $modulesCount, $activeLicensesCount, 1);
    $chartBars = [
        ['label' => 'مشتری‌ها', 'value' => $customers,        'color' => '#7c3aed'],
        ['label' => 'سایت‌ها',  'value' => $sites,            'color' => '#0284c7'],
        ['label' => 'فرم‌ها',   'value' => $forms,             'color' => '#059669'],
        ['label' => 'ماژول‌ها', 'value' => $modulesCount,      'color' => '#e11d48'],
        ['label' => 'لایسنس',   'value' => $activeLicensesCount,'color' => '#d97706'],
    ];
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

/* Pure CSS bar chart */
.css-chart{display:flex;align-items:flex-end;gap:8px;height:130px;padding:0 4px}
.css-bar-wrap{display:flex;flex-direction:column;align-items:center;flex:1;height:100%;justify-content:flex-end;gap:4px}
.css-bar{width:100%;border-radius:4px 4px 0 0;min-height:4px;transition:height .4s ease}
.css-bar-val{font-size:.7rem;font-weight:700;color:#1e293b;direction:ltr}
.css-bar-lbl{font-size:.6rem;color:#64748b;text-align:center;white-space:nowrap}

/* Expiry badge */
.expiry-urgent{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3)}
.expiry-warning{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3)}
.expiry-ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3)}
</style>
@endpush

{{-- ── Header ── --}}
<div class="flex items-center justify-between mb-4 px-1">
    <div>
        <h2 class="text-xl font-bold text-gray-900">خوش آمدید، <span class="bg-linear-to-l from-indigo-600 to-purple-600 bg-clip-text text-transparent">{{ auth()->user()->name ?? 'مدیر' }}</span></h2>
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

{{-- ── Chart (full-width, pure inline-style bars — zero CDN dependency) ── --}}
<div class="glass-card-strong rounded-xl p-4 mb-4">
    <div class="flex items-center gap-2 mb-3">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 text-base">📊</span>
        <span class="font-semibold text-gray-800 text-sm">آمار کلی</span>
        <span class="ms-auto text-xs text-gray-400">{{ \App\Helpers\Jalali::formatFa(now(), 'Y/m/d') }}</span>
    </div>

    @php
        $barHeightMax = 90; // max bar height in px
        $chartBarsData = [
            ['label' => 'مشتری‌ها', 'value' => $customers,         'color' => '#7c3aed'],
            ['label' => 'سایت‌ها',  'value' => $sites,             'color' => '#0284c7'],
            ['label' => 'فرم‌ها',   'value' => $forms,              'color' => '#059669'],
            ['label' => 'ماژول‌ها', 'value' => $modulesCount,       'color' => '#e11d48'],
            ['label' => 'لایسنس',   'value' => $activeLicensesCount,'color' => '#d97706'],
        ];
        $maxVal = max(array_column($chartBarsData, 'value'));
        $maxVal = max($maxVal, 1);
    @endphp

    {{-- Value labels row --}}
    <div style="display:flex;gap:12px;margin-bottom:6px;padding:0 4px">
        @foreach($chartBarsData as $bar)
        <div style="flex:1;text-align:center;font-size:.75rem;font-weight:800;color:#1e293b;font-family:Vazirmatn,sans-serif">
            {{ \App\Helpers\NumberHelper::formatFa($bar['value']) }}
        </div>
        @endforeach
    </div>

    {{-- Bar columns — pixel heights, no percentage --}}
    <div style="display:flex;gap:12px;align-items:flex-end;height:{{ $barHeightMax }}px;padding:0 4px;margin-bottom:6px">
        @foreach($chartBarsData as $bar)
        @php $px = max(4, (int) round(($bar['value'] / $maxVal) * $barHeightMax)); @endphp
        <div style="flex:1;height:{{ $px }}px;background:{{ $bar['color'] }};border-radius:5px 5px 0 0;opacity:.88;transition:height .4s ease"></div>
        @endforeach
    </div>

    {{-- Label row --}}
    <div style="display:flex;gap:12px;padding:0 4px;border-top:1px solid #f1f5f9;padding-top:6px">
        @foreach($chartBarsData as $bar)
        <div style="flex:1;text-align:center;font-size:.65rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:Vazirmatn,sans-serif">
            {{ $bar['label'] }}
        </div>
        @endforeach
    </div>

    {{-- Summary stats --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px">
        <div style="display:flex;align-items:center;gap:6px;font-size:.72rem">
            <span style="width:8px;height:8px;border-radius:50%;background:#059669;flex-shrink:0"></span>
            <span style="color:#64748b">فاکتور پرداخت‌شده:</span>
            <span style="font-weight:700;color:#1e293b">{{ \App\Helpers\NumberHelper::formatFa($paidInvoicesCount) }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:.72rem">
            <span style="width:8px;height:8px;border-radius:50%;background:#d97706;flex-shrink:0"></span>
            <span style="color:#64748b">درآمد این ماه:</span>
            <span style="font-weight:700;color:#1e293b">{{ $revenueThisMonth > 0 ? num_fa($revenueThisMonth) . ' ت' : '—' }}</span>
        </div>
    </div>
</div>

{{-- ── Expiry warnings (real-time from Site.host_expires_at) ── --}}
@if($activeExpiryWarnings->isNotEmpty())
<div class="glass-card-strong rounded-xl p-4 mb-4 border-r-4 border-r-amber-400">
    <div class="flex items-center gap-2 mb-3">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 text-base">⚠</span>
        <span class="font-semibold text-gray-800 text-sm">هشدارهای انقضا</span>
        <span class="ms-auto text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold">{{ $activeExpiryWarnings->count() }}</span>
    </div>
    <ul class="space-y-1.5 max-h-44 overflow-y-auto scrollbar-thin">
        @foreach($activeExpiryWarnings as $warn)
        @php
            $daysLeft     = $warn->days_left;
            $urgencyBg    = $daysLeft <= 7 ? 'rgba(239,68,68,.1)' : ($daysLeft <= 14 ? 'rgba(245,158,11,.1)' : 'rgba(16,185,129,.1)');
            $urgencyBdr   = $daysLeft <= 7 ? 'rgba(239,68,68,.3)' : ($daysLeft <= 14 ? 'rgba(245,158,11,.3)' : 'rgba(16,185,129,.3)');
            $dotBg        = $daysLeft <= 7 ? '#ef4444' : ($daysLeft <= 14 ? '#f59e0b' : '#10b981');
            $daysColor    = $daysLeft <= 7 ? '#dc2626' : ($daysLeft <= 14 ? '#d97706' : '#059669');
            $daysText     = $daysLeft === 0 ? 'امروز منقضی می‌شود' : \App\Helpers\NumberHelper::toPersianNumbers($daysLeft) . ' روز مانده';
            $expDateFa    = \App\Helpers\Jalali::formatFa($warn->expires_at, 'Y/m/d');
        @endphp
        <li style="display:flex;align-items:flex-start;gap:8px;padding:8px;border-radius:8px;background:{{ $urgencyBg }};border:1px solid {{ $urgencyBdr }}">
            <span style="flex-shrink:0;width:6px;height:6px;border-radius:50%;background:{{ $dotBg }};margin-top:5px"></span>
            <div style="min-width:0;flex:1">
                <span style="font-size:.75rem;font-weight:600;color:#1e293b">{{ $warn->site->title ?? '—' }}</span>
                <span style="font-size:.75rem;color:#94a3b8;margin:0 4px">—</span>
                <span style="font-size:.75rem;color:#475569">{{ $warn->label }}</span>
                <div style="display:flex;align-items:center;gap:8px;margin-top:2px">
                    <span style="font-size:.7rem;font-weight:700;color:{{ $daysColor }}">{{ $daysText }}</span>
                    <span style="font-size:.65rem;color:#94a3b8">({{ $expDateFa }})</span>
                </div>
            </div>
            @if($warn->site)
            <a href="{{ route('admin.sites.edit', $warn->site) }}" style="flex-shrink:0;font-size:.7rem;color:#6366f1;text-decoration:underline;white-space:nowrap">ویرایش</a>
            @endif
        </li>
        @endforeach
    </ul>
</div>
@endif

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

{{-- ── Guide — always visible, never in a collapsible ── --}}
<div class="glass-card-strong rounded-xl p-4 mb-3">
    @include('admin.dashboard.guide')
</div>

@endsection
