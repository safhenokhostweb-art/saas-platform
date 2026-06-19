@extends('layouts.admin')

@section('header', 'مرکز مانیتورینگ')

@push('head')
<style>
/* ======== Layout ======== */
.mon-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem}
.mon-grid-5{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem}
.mon-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:1100px){.mon-grid-4{grid-template-columns:repeat(2,1fr)}.mon-grid-5{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.mon-grid-4,.mon-grid-5,.mon-grid-2{grid-template-columns:1fr}}

/* ======== Stat Cards ======== */
.mon-stat{border-radius:1rem;padding:1.25rem 1.5rem;color:#fff;text-decoration:none;display:block;transition:all .25s;border:1px solid rgba(255,255,255,.1)}
.mon-stat:hover{transform:translateY(-3px);box-shadow:0 16px 40px rgba(15,23,42,.18)}
.mon-stat--blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}
.mon-stat--green{background:linear-gradient(135deg,#059669,#064e3b)}
.mon-stat--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.mon-stat--red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.mon-stat--violet{background:linear-gradient(135deg,#7c3aed,#2e1065)}
.mon-stat__label{font-size:.78rem;font-weight:500;opacity:.85;margin-bottom:.35rem}
.mon-stat__value{font-size:2.1rem;font-weight:800;line-height:1}
.mon-stat__sub{font-size:.72rem;opacity:.7;margin-top:.3rem}

/* ======== Platform Health Badges ======== */
.ph-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,.05);padding:1.25rem 1.5rem}
.ph-item{display:flex;align-items:center;gap:.875rem;padding:.7rem 1rem;border-radius:.75rem;border:1px solid #e5e7eb;background:#f9fafb;margin-bottom:.5rem}
.ph-item:last-child{margin-bottom:0}
.ph-dot{width:.75rem;height:.75rem;border-radius:50%;flex-shrink:0}
.ph-dot--online{background:#10b981}
.ph-dot--warning{background:#f59e0b}
.ph-dot--critical{background:#ef4444}
.ph-dot--unknown{background:#94a3b8}
.ph-name{font-weight:600;font-size:.875rem;color:#1f2937;flex:1}
.ph-msg{font-size:.78rem;color:#6b7280}

/* ======== Status Badge (inline) ======== */
.badge-status{display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.2rem .6rem;border-radius:9999px}
.badge-status--healthy{background:#d1fae5;color:#065f46}
.badge-status--warning{background:#fef3c7;color:#92400e}
.badge-status--critical{background:#fee2e2;color:#991b1b}
.badge-status--unknown{background:#f1f5f9;color:#64748b}

/* ======== Site Table ======== */
.mon-table{width:100%;border-collapse:collapse;font-size:.82rem}
.mon-table th{text-align:right;padding:.6rem 1rem;background:#f8fafc;color:#64748b;font-weight:600;border-bottom:2px solid #e2e8f0;white-space:nowrap}
.mon-table td{padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.mon-table tr:hover td{background:#f8faff}
.mon-table .col-icon{display:flex;align-items:center;gap:.5rem;font-weight:600;color:#1f2937}
.col-domain{font-family:monospace;font-size:.8rem;color:#4f46e5}
.col-ms{font-size:.8rem;color:#374151;font-family:monospace}

/* ======== Incidents ======== */
.inc-row{display:flex;align-items:flex-start;gap:.875rem;padding:.875rem 1rem;border-radius:.75rem;margin-bottom:.5rem;border-left:4px solid}
.inc-row--critical{background:#fff1f2;border-color:#ef4444}
.inc-row--high{background:#fff7ed;border-color:#f97316}
.inc-row--medium{background:#fffbeb;border-color:#f59e0b}
.inc-row--low{background:#f0fdf4;border-color:#22c55e}
.inc-row--warning{background:#fffbeb;border-color:#f59e0b}
.inc-icon{width:2rem;height:2rem;border-radius:.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.inc-icon--critical{background:#fee2e2;color:#dc2626}
.inc-icon--high{background:#ffedd5;color:#ea580c}
.inc-icon--medium{background:#fef3c7;color:#d97706}
.inc-icon--low{background:#dcfce7;color:#16a34a}
.inc-icon--warning{background:#fef3c7;color:#d97706}

/* ======== Resource Meters ======== */
.res-meter-wrap{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-top:.5rem}
.res-meter{display:flex;flex-direction:column;gap:.3rem}
.res-meter-label{font-size:.7rem;color:#6b7280;font-weight:600}
.res-bar{height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden}
.res-bar-fill{height:100%;border-radius:3px;transition:width .3s}
.res-bar-fill--ok{background:#10b981}
.res-bar-fill--warning{background:#f59e0b}
.res-bar-fill--critical{background:#ef4444}
.res-meter-val{font-size:.78rem;font-weight:700;color:#1f2937}

/* ======== Scheduler + Backup ======== */
.sch-table{width:100%;border-collapse:collapse;font-size:.8rem}
.sch-table th{text-align:right;padding:.5rem .75rem;background:#f8fafc;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0}
.sch-table td{padding:.6rem .75rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.sch-table tr:last-child td{border-bottom:none}
.sch-dot{display:inline-block;width:.55rem;height:.55rem;border-radius:50%;margin-left:.3rem}
.sch-dot--success{background:#10b981}.sch-dot--failed{background:#ef4444}.sch-dot--never{background:#94a3b8}
.inc-icon--warning{background:#fef3c7;color:#d97706}
.inc-title{font-weight:700;font-size:.875rem;color:#1f2937}
.inc-meta{font-size:.75rem;color:#6b7280;margin-top:.15rem}
.inc-msg{font-size:.78rem;color:#374151;margin-top:.25rem}

/* ======== Charts ======== */
.chart-bar-wrap{display:flex;align-items:flex-end;gap:3px;height:80px;padding:0 4px}
.chart-bar{flex:1;border-radius:3px 3px 0 0;transition:all .2s;cursor:default}
.chart-bar--healthy{background:#10b981}
.chart-bar--warning{background:#f59e0b}
.chart-bar--critical{background:#ef4444}

/* ======== Refresh pulse ======== */
.pulse-dot{width:.6rem;height:.6rem;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 1.8s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">مرکز مانیتورینگ</h1>
        <p class="text-gray-500 text-sm mt-1 flex items-center gap-2">
            <span class="pulse-dot"></span>
            آخرین بررسی: {{ now()->format('H:i:s') }}
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.infrastructure.monitoring.index') }}"
           class="btn btn-secondary text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            بروزرسانی
        </a>
        <a href="{{ route('admin.infrastructure.overview') }}"
           class="btn btn-secondary text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            نمای کلی زیرساخت
        </a>
    </div>
</div>

{{-- Critical Alert Banner --}}
@if($criticalCount > 0)
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-3">
    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div>
        <p class="font-bold text-red-800">{{ \App\Helpers\NumberHelper::formatFa($criticalCount) }} رویداد بحرانی باز وجود دارد</p>
        <p class="text-red-600 text-sm">فوری بررسی کنید — جزئیات در بخش رویدادها</p>
    </div>
</div>
@endif

{{-- ======== Platform Status Cards ======== --}}
<div class="mon-grid-4 mb-6">
    <div class="mon-stat mon-stat--blue">
        <div class="mon-stat__label">کل سایت‌ها</div>
        <div class="mon-stat__value">{{ \App\Helpers\NumberHelper::formatFa($totalSites) }}</div>
        <div class="mon-stat__sub">سایت فعال</div>
    </div>
    <div class="mon-stat mon-stat--green">
        <div class="mon-stat__label">سالم</div>
        <div class="mon-stat__value">{{ \App\Helpers\NumberHelper::formatFa($healthySites) }}</div>
        <div class="mon-stat__sub">سایت سالم</div>
    </div>
    <div class="mon-stat mon-stat--amber">
        <div class="mon-stat__label">هشدار</div>
        <div class="mon-stat__value">{{ \App\Helpers\NumberHelper::formatFa($warningSites) }}</div>
        <div class="mon-stat__sub">نیازمند توجه</div>
    </div>
    <div class="mon-stat {{ $criticalSites > 0 ? 'mon-stat--red' : 'mon-stat--violet' }}">
        <div class="mon-stat__label">بحرانی</div>
        <div class="mon-stat__value">{{ \App\Helpers\NumberHelper::formatFa($criticalSites) }}</div>
        <div class="mon-stat__sub">سایت در مشکل</div>
    </div>
</div>

{{-- ======== Infrastructure Health + Trend Charts ======== --}}
<div class="mon-grid-2 mb-6">

    {{-- Platform Health --}}
    <div class="ph-card">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
            </svg>
            سلامت زیرساخت پلتفرم
        </h3>

        @php
        $componentNames = [
            'database' => 'دیتابیس MySQL',
            'redis'    => 'Redis Cache',
            'queue'    => 'Queue Workers',
            'reverb'   => 'Reverb WebSocket',
            'web'      => 'وب‌سرور',
            'failed_jobs' => 'Failed Jobs',
            'sms'      => 'ارسال پیامک',
            'ai'       => 'سرویس هوش مصنوعی',
            'storage'  => 'حافظه پشتیبان',
        ];
        $statusIcons = [
            'online'    => 'ph-dot--online',
            'warning'   => 'ph-dot--warning',
            'critical'  => 'ph-dot--critical',
            'disabled'  => 'ph-dot--unknown',
        ];
        $statusLabels = [
            'online'    => 'آنلاین',
            'warning'   => 'هشدار',
            'critical'  => 'بحرانی',
            'disabled'  => 'غیرفعال',
        ];
        @endphp

        @foreach($componentNames as $key => $label)
            @if(isset($platformChecks[$key]))
                @php $check = $platformChecks[$key]; $st = $check['status']; @endphp
                <div class="ph-item">
                    <span class="ph-dot {{ $statusIcons[$st] ?? 'ph-dot--unknown' }}"></span>
                    <span class="ph-name">{{ $label }}</span>
                    @php $badgeClass = match($st) { 'online' => 'healthy', 'warning' => 'warning', 'critical' => 'critical', default => 'unknown' }; @endphp
                    <span class="badge-status badge-status--{{ $badgeClass }}">
                        {{ $statusLabels[$st] ?? $st }}
                    </span>
                    <span class="ph-msg">{{ Str::limit($check['message'] ?? '', 40) }}</span>
                </div>
            @endif
        @endforeach

        {{-- Resource Metrics from PlatformLog --}}
        @if($platformLog)
        <div style="margin-top:.75rem;padding:.875rem 1rem;border-radius:.75rem;border:1px solid #e5e7eb;background:#f9fafb">
            <div class="ph-name" style="font-size:.78rem;color:#6b7280;font-weight:600;margin-bottom:.6rem;">منابع سرور (آخرین اندازه‌گیری)</div>
            <div class="res-meter-wrap">
                @php
                    $disk = $platformLog->disk_used_percent ?? 0;
                    $mem  = $platformLog->memory_used_percent ?? 0;
                    $cpu  = round($platformLog->cpu_load_1m ?? 0, 1);
                    $diskClass = $disk >= 90 ? 'critical' : ($disk >= 75 ? 'warning' : 'ok');
                    $memClass  = $mem  >= 90 ? 'critical' : ($mem  >= 75 ? 'warning' : 'ok');
                    $cpuPct    = min(100, $cpu * 10);
                    $cpuClass  = $cpu >= 8 ? 'critical' : ($cpu >= 5 ? 'warning' : 'ok');
                @endphp
                <div class="res-meter">
                    <span class="res-meter-label">دیسک</span>
                    <div class="res-bar"><div class="res-bar-fill res-bar-fill--{{ $diskClass }}" style="width:{{ $disk }}%"></div></div>
                    <span class="res-meter-val">{{ \App\Helpers\NumberHelper::formatFa($disk) }}٪</span>
                </div>
                <div class="res-meter">
                    <span class="res-meter-label">حافظه RAM</span>
                    <div class="res-bar"><div class="res-bar-fill res-bar-fill--{{ $memClass }}" style="width:{{ $mem }}%"></div></div>
                    <span class="res-meter-val">{{ \App\Helpers\NumberHelper::formatFa($mem) }}٪</span>
                </div>
                <div class="res-meter">
                    <span class="res-meter-label">بار CPU</span>
                    <div class="res-bar"><div class="res-bar-fill res-bar-fill--{{ $cpuClass }}" style="width:{{ $cpuPct }}%"></div></div>
                    <span class="res-meter-val">{{ \App\Helpers\NumberHelper::formatFa($cpu) }}</span>
                </div>
            </div>
            @if($platformLog->failed_jobs > 0)
            <div style="margin-top:.6rem;font-size:.75rem;background:#fee2e2;color:#991b1b;padding:.35rem .75rem;border-radius:.5rem;font-weight:600;">
                ⚠ {{ \App\Helpers\NumberHelper::formatFa($platformLog->failed_jobs) }} کار ناموفق در صف
            </div>
            @endif
        </div>
        @endif

        <div class="mt-4 pt-3 border-t border-gray-100">
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span>وضعیت کلی پلتفرم:</span>
                <span class="badge-status badge-status--{{ $platformStatus === 'online' ? 'healthy' : $platformStatus }} text-xs font-bold">
                    @if($platformStatus === 'online') همه سرویس‌ها آنلاین
                    @elseif($platformStatus === 'warning') یک یا چند هشدار
                    @else بحران فعال @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Response Time Trend --}}
    <div class="ph-card">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            روند زمان پاسخ (۲۴ ساعت اخیر)
        </h3>
        @if($responseTrend->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-sm">هنوز داده‌ای ثبت نشده</p>
                <p class="text-xs mt-1">اجرای <code>monitor:sites</code> داده‌ها را پر می‌کند</p>
            </div>
        @else
            @php
                $maxMs = $responseTrend->max('avg_ms') ?: 1;
            @endphp
            <div class="chart-bar-wrap" x-data>
                @foreach($responseTrend as $point)
                    @php
                        $h = max(4, (int) ($point->avg_ms / $maxMs * 76));
                        $cls = $point->avg_ms > 2000 ? 'chart-bar--critical' : ($point->avg_ms > 800 ? 'chart-bar--warning' : 'chart-bar--healthy');
                    @endphp
                    <div class="chart-bar {{ $cls }}"
                         style="height:{{ $h }}px"
                         title="{{ $point->hour }} — {{ number_format($point->avg_ms) }}ms"></div>
                @endforeach
            </div>
            <div class="flex justify-between text-xs text-gray-400 mt-1 px-1">
                <span>{{ $responseTrend->first()->hour ?? '' }}</span>
                <span>{{ $responseTrend->last()->hour ?? '' }}</span>
            </div>
            <div class="mt-3 flex gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-green-400 inline-block"></span> خوب &lt;800ms</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-400 inline-block"></span> هشدار &lt;2000ms</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-red-400 inline-block"></span> بد &gt;2000ms</span>
            </div>
        @endif
    </div>

</div>

{{-- ======== Scheduler Health + Backup Status ======== --}}
<div class="mon-grid-2 mb-6">

    {{-- Scheduler Health --}}
    <div class="ph-card">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            وضعیت زمان‌بند (Scheduler)
        </h3>
        @if(empty($schedulerRuns))
            <p class="text-sm text-gray-400 text-center py-6">هنوز هیچ دستوری ثبت نشده</p>
        @else
        <table class="sch-table">
            <thead>
                <tr>
                    <th>دستور</th>
                    <th>آخرین اجرا</th>
                    <th>مدت</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedulerRuns as $cmd => $run)
                <tr>
                    <td style="font-family:monospace;font-size:.73rem;color:#374151;">{{ $cmd }}</td>
                    <td style="color:#6b7280;font-size:.75rem">
                        @if($run)
                            {{ $run->started_at ? \App\Helpers\Jalali::diffForHumansFa($run->started_at) : '—' }}
                        @else
                            <span style="color:#94a3b8">اجرا نشده</span>
                        @endif
                    </td>
                    <td style="color:#6b7280;font-size:.75rem">
                        @if($run && $run->duration_ms)
                            {{ \App\Helpers\NumberHelper::formatFa($run->duration_ms / 1000, 1) }}ث
                        @else —
                        @endif
                    </td>
                    <td>
                        @if(! $run)
                            <span class="sch-dot sch-dot--never"></span><span style="color:#94a3b8;font-size:.75rem">بدون سابقه</span>
                        @elseif($run->status === 'success')
                            <span class="sch-dot sch-dot--success"></span><span style="color:#059669;font-size:.75rem">موفق</span>
                        @else
                            <span class="sch-dot sch-dot--failed"></span><span style="color:#dc2626;font-size:.75rem">خطا</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Backup Status --}}
    <div class="ph-card">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
            </svg>
            آخرین پشتیبان‌گیری
        </h3>
        @if(! $latestBackup)
            <div class="text-center py-8 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                </svg>
                <p class="text-sm">هنوز پشتیبانی انجام نشده</p>
                <p class="text-xs mt-1">دستور <code class="bg-gray-100 px-1 rounded">php artisan db:backup</code> را اجرا کنید</p>
            </div>
        @else
            @php
                $bStatus = $latestBackup->status;
                $bColor  = $bStatus === 'success' ? '#059669' : '#dc2626';
                $bBg     = $bStatus === 'success' ? '#d1fae5' : '#fee2e2';
            @endphp
            <div style="display:flex;flex-direction:column;gap:.875rem">
                <div style="display:flex;align-items:center;gap:.875rem;padding:1rem;border-radius:.75rem;background:{{ $bBg }}">
                    <div style="width:2.5rem;height:2.5rem;border-radius:.75rem;background:rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        @if($bStatus === 'success')
                            <svg class="w-5 h-5" style="color:{{ $bColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="w-5 h-5" style="color:{{ $bColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                    <div>
                        <div style="font-weight:700;color:{{ $bColor }};font-size:.875rem">{{ $bStatus === 'success' ? 'موفق' : 'ناموفق' }}</div>
                        <div style="font-size:.75rem;color:#374151;margin-top:.15rem">{{ $latestBackup->started_at ? \App\Helpers\Jalali::diffForHumansFa($latestBackup->started_at) : '' }}</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;font-size:.8rem">
                    <div>
                        <div style="color:#9ca3af;font-size:.7rem;font-weight:600;margin-bottom:.2rem">نوع</div>
                        <div style="color:#1f2937;font-weight:500">{{ $latestBackup->backup_type }}</div>
                    </div>
                    @if($latestBackup->file_size_mb)
                    <div>
                        <div style="color:#9ca3af;font-size:.7rem;font-weight:600;margin-bottom:.2rem">حجم فایل</div>
                        <div style="color:#1f2937;font-weight:500">{{ \App\Helpers\NumberHelper::formatFa($latestBackup->file_size_mb, 1) }} MB</div>
                    </div>
                    @endif
                    @if($latestBackup->duration_seconds)
                    <div>
                        <div style="color:#9ca3af;font-size:.7rem;font-weight:600;margin-bottom:.2rem">مدت اجرا</div>
                        <div style="color:#1f2937;font-weight:500">{{ \App\Helpers\NumberHelper::formatFa($latestBackup->duration_seconds) }}ث</div>
                    </div>
                    @endif
                    <div>
                        <div style="color:#9ca3af;font-size:.7rem;font-weight:600;margin-bottom:.2rem">زمان شروع</div>
                        <div style="color:#1f2937;font-weight:500">{{ \App\Helpers\Jalali::formatFa($latestBackup->started_at, 'Y/m/d H:i') }}</div>
                    </div>
                </div>
                @if($latestBackup->error_message)
                <div style="font-size:.75rem;background:#fee2e2;color:#991b1b;padding:.5rem .75rem;border-radius:.5rem;border:1px solid #fca5a5;">
                    {{ Str::limit($latestBackup->error_message, 120) }}
                </div>
                @endif
            </div>
        @endif
    </div>

</div>

{{-- ======== Site Status Table ======== --}}
<div class="ph-card mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
            </svg>
            وضعیت سایت‌ها
        </h3>
        <span class="text-sm text-gray-400">{{ \App\Helpers\NumberHelper::formatFa($latestLogs->count()) }} سایت بررسی‌شده</span>
    </div>

    @if($latestLogs->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
            </svg>
            <p class="font-medium">هنوز هیچ سایتی بررسی نشده</p>
            <p class="text-sm mt-1">دستور <code class="bg-gray-100 px-1 rounded">php artisan monitor:sites</code> را اجرا کنید</p>
        </div>
    @else
        <div style="overflow-x:auto">
        <table class="mon-table">
            <thead>
                <tr>
                    <th>سایت</th>
                    <th>دامنه</th>
                    <th>وضعیت</th>
                    <th>آخرین بررسی</th>
                    <th>زمان پاسخ</th>
                    <th>SSL</th>
                    <th>دامنه</th>
                    <th>Queue</th>
                    <th>دیتابیس</th>
                    <th>Redis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($latestLogs as $log)
                <tr>
                    <td>
                        <div class="col-icon">
                            @if($log->status === 'healthy')
                                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @elseif($log->status === 'warning')
                                <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            @else
                                <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            @endif
                            <a href="{{ route('admin.infrastructure.monitoring.site', $log->site_id) }}" style="color:#1f2937;text-decoration:none;font-weight:600;hover:color:#4f46e5">{{ $log->site->title ?? "سایت #{$log->site_id}" }}</a>
                        </div>
                    </td>
                    <td><span class="col-domain">{{ $log->domain }}</span></td>
                    <td>
                        <span class="badge-status badge-status--{{ $log->status }}">
                            @if($log->status === 'healthy') سالم
                            @elseif($log->status === 'warning') هشدار
                            @elseif($log->status === 'critical') بحرانی
                            @else نامشخص @endif
                        </span>
                    </td>
                    <td class="text-xs text-gray-500">
                        {{ $log->checked_at ? \App\Helpers\Jalali::diffForHumansFa($log->checked_at) : '—' }}
                    </td>
                    <td>
                        @if($log->response_time_ms !== null)
                            @php $ms = $log->response_time_ms; @endphp
                            <span class="col-ms {{ $ms > 2000 ? 'text-red-600 font-bold' : ($ms > 800 ? 'text-amber-600' : 'text-green-600') }}">
                                {{ number_format($ms) }}ms
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td>
                        @php $ssl = $log->ssl_status ?? 'unknown'; @endphp
                        <span class="badge-status {{ $ssl === 'valid' ? 'badge-status--healthy' : ($ssl === 'expiring_soon' ? 'badge-status--warning' : ($ssl === 'critical' || $ssl === 'invalid' ? 'badge-status--critical' : 'badge-status--unknown')) }}">
                            @if($ssl === 'valid') معتبر @elseif($ssl === 'expiring_soon') رو به انقضا @elseif($ssl === 'critical') بحرانی @elseif($ssl === 'invalid') نامعتبر @else نامشخص @endif
                            @if($log->ssl_days_remaining !== null) ({{ $log->ssl_days_remaining }}روز) @endif
                        </span>
                    </td>
                    <td>
                        @if($log->domain_days_remaining !== null)
                            @php $dd = $log->domain_days_remaining; @endphp
                            <span class="badge-status {{ $dd < 7 ? 'badge-status--critical' : ($dd < 14 ? 'badge-status--warning' : 'badge-status--healthy') }}">
                                {{ \App\Helpers\NumberHelper::formatFa($dd) }}روز
                            </span>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td>
                        @php $q = $log->queue_status ?? 'unknown'; @endphp
                        <span class="badge-status {{ $q === 'ok' ? 'badge-status--healthy' : ($q === 'idle' ? 'badge-status--warning' : ($q === 'error' ? 'badge-status--critical' : 'badge-status--unknown')) }}">
                            @if($q === 'ok') فعال @elseif($q === 'idle') خاموش @elseif($q === 'error') خطا @else نامشخص @endif
                        </span>
                    </td>
                    <td>
                        @php $db = $log->db_status ?? 'unknown'; @endphp
                        <span class="badge-status {{ $db === 'ok' ? 'badge-status--healthy' : ($db === 'error' ? 'badge-status--critical' : 'badge-status--unknown') }}">
                            @if($db === 'ok') متصل @elseif($db === 'error') خطا @else نامشخص @endif
                        </span>
                    </td>
                    <td>
                        @php $rd = $log->redis_status ?? 'unknown'; @endphp
                        <span class="badge-status {{ $rd === 'ok' ? 'badge-status--healthy' : ($rd === 'error' ? 'badge-status--critical' : 'badge-status--unknown') }}">
                            @if($rd === 'ok') متصل @elseif($rd === 'error') خطا @else نامشخص @endif
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('admin.infrastructure.monitoring.site', $log->site_id) }}" style="color:#6366f1;font-size:.75rem;text-decoration:none;white-space:nowrap">جزئیات ↗</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

{{-- ======== Open Incidents ======== --}}
<div class="ph-card mb-6" x-data="{ resolving: null }">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            رویدادهای باز
            @if($openIncidents->isNotEmpty())
                <span class="ml-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-700 text-xs font-bold">{{ \App\Helpers\NumberHelper::formatFa($openIncidents->count()) }}</span>
            @endif
        </h3>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.incidents.index') }}"
               class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                مدیریت حوادث ↗
            </a>
            <a href="{{ route('admin.api.monitoring.incidents') }}" target="_blank"
               class="text-xs text-gray-400 hover:underline flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                JSON
            </a>
        </div>
    </div>

    @if($openIncidents->isEmpty())
        <div class="text-center py-8 text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="font-medium text-green-600">همه رویدادها حل شده‌اند</p>
        </div>
    @else
        @foreach($openIncidents as $incident)
        <div class="inc-row inc-row--{{ $incident->severity }}" id="incident-{{ $incident->id }}">
            <div class="inc-icon inc-icon--{{ $incident->severity }}">
                @if($incident->severity === 'critical')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="inc-title">{{ $incident->title }}</div>
                <div class="inc-meta">
                    {{ $incident->site->title ?? 'پلتفرم' }} ·
                    {{ \App\Helpers\Jalali::diffForHumansFa($incident->created_at) }} ·
                    <span class="font-mono text-xs">{{ $incident->type }}</span>
                </div>
                @if($incident->message)
                <div class="inc-msg">{{ Str::limit($incident->message, 120) }}</div>
                @endif
            </div>
            <div class="flex-shrink-0 flex flex-col gap-1.5">
                <a href="{{ route('admin.incidents.show', $incident->id) }}"
                   class="text-xs px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition-colors text-center block">
                    جزئیات
                </a>
                <button
                    onclick="resolveIncident({{ $incident->id }}, this)"
                    class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-white hover:border-green-400 hover:text-green-700 transition-colors">
                    حل شد
                </button>
            </div>
        </div>
        @endforeach
    @endif
</div>

{{-- ======== Incident Trend ======== --}}
<div class="ph-card mb-6">
    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        رویدادها — ۷ روز اخیر
    </h3>
    @if($incidentTrend->isEmpty())
        <p class="text-sm text-gray-400 text-center py-4">هیچ رویدادی در ۷ روز اخیر ثبت نشده</p>
    @else
        @php $maxInc = $incidentTrend->max('total') ?: 1; @endphp
        <div class="chart-bar-wrap">
            @foreach($incidentTrend as $point)
                @php $h = max(4, (int)($point->total / $maxInc * 76)); @endphp
                <div class="chart-bar chart-bar--warning" style="height:{{ $h }}px"
                     title="{{ $point->day }} — {{ $point->total }} رویداد"></div>
            @endforeach
        </div>
        <div class="flex justify-between text-xs text-gray-400 mt-1 px-1">
            <span>{{ $incidentTrend->first()->day ?? '' }}</span>
            <span>{{ $incidentTrend->last()->day ?? '' }}</span>
        </div>
    @endif
</div>

{{-- ======== Quick Links ======== --}}
<div class="mon-grid-4">
    <a href="{{ route('admin.api.monitoring.summary') }}" target="_blank"
       class="ph-card flex items-center gap-3 text-decoration-none hover:border-indigo-300 transition-colors cursor-pointer">
        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </div>
        <div>
            <p class="font-semibold text-sm text-gray-800">API خلاصه</p>
            <p class="text-xs text-gray-400">JSON summary</p>
        </div>
    </a>
    <a href="{{ route('admin.api.monitoring.sites') }}" target="_blank"
       class="ph-card flex items-center gap-3 hover:border-violet-300 transition-colors cursor-pointer">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
        </div>
        <div>
            <p class="font-semibold text-sm text-gray-800">API سایت‌ها</p>
            <p class="text-xs text-gray-400">JSON sites status</p>
        </div>
    </a>
    <a href="{{ route('admin.api.monitoring.incidents') }}" target="_blank"
       class="ph-card flex items-center gap-3 hover:border-red-300 transition-colors cursor-pointer">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <div>
            <p class="font-semibold text-sm text-gray-800">API رویدادها</p>
            <p class="text-xs text-gray-400">JSON incidents</p>
        </div>
    </a>
    <a href="{{ route('admin.infrastructure.sms') }}"
       class="ph-card flex items-center gap-3 hover:border-blue-300 transition-colors cursor-pointer">
        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <div>
            <p class="font-semibold text-sm text-gray-800">لاگ پیامک</p>
            <p class="text-xs text-gray-400">SMS delivery log</p>
        </div>
    </a>
</div>

@push('scripts')
<script>
function resolveIncident(id, btn) {
    btn.disabled = true;
    btn.textContent = '...';
    fetch(`/admin/api/monitoring/incidents/${id}/resolve`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const row = document.getElementById('incident-' + id);
            if (row) {
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'حل شد';
    });
}
</script>
@endpush

@endsection
