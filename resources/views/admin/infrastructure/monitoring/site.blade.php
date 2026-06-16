@extends('layouts.admin')

@section('header', 'وضعیت سایت: ' . $site->title)

@push('head')
<style>
/* Layout */
.site-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;margin-bottom:1.5rem}
.site-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem}
@media(max-width:1100px){.site-grid{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.site-grid,.site-grid-2{grid-template-columns:1fr}}

/* Cards */
.s-card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 2px 12px rgba(15,23,42,.04);padding:1.25rem 1.5rem;margin-bottom:0}
.s-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
.s-card-head h3{font-size:.9rem;font-weight:700;color:#1f2937;margin:0;display:flex;align-items:center;gap:.5rem}

/* Uptime Stat Cards */
.uptime-card{background:linear-gradient(135deg,#4f46e5,#2563eb);color:#fff;border-radius:1rem;padding:1.25rem 1.5rem;text-align:center}
.uptime-card.good{background:linear-gradient(135deg,#059669,#047857)}
.uptime-card.warn{background:linear-gradient(135deg,#d97706,#92400e)}
.uptime-card.bad{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.uptime-pct{font-size:2rem;font-weight:800;line-height:1}
.uptime-label{font-size:.75rem;opacity:.8;margin-top:.3rem}

/* Uptime History */
.uptime-hist{display:flex;align-items:flex-end;gap:3px;height:48px}
.uptime-bar{flex:1;border-radius:2px 2px 0 0;cursor:default;min-height:4px}
.uptime-bar--healthy{background:#10b981}
.uptime-bar--warning{background:#f59e0b}
.uptime-bar--critical{background:#ef4444}
.uptime-bar--unknown{background:#e5e7eb}

/* Status Badges */
.badge-s{display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.2rem .6rem;border-radius:9999px}
.badge-s--healthy{background:#d1fae5;color:#065f46}
.badge-s--warning{background:#fef3c7;color:#92400e}
.badge-s--critical{background:#fee2e2;color:#991b1b}
.badge-s--unknown{background:#f1f5f9;color:#64748b}

/* Severity */
.sev-badge{display:inline-flex;align-items:center;font-size:.72rem;font-weight:700;padding:.2rem .65rem;border-radius:9999px}
.sev-critical{background:#fee2e2;color:#991b1b}
.sev-high{background:#ffedd5;color:#9a3412}
.sev-medium{background:#fef3c7;color:#92400e}
.sev-low{background:#d1fae5;color:#065f46}

/* Status (incident lifecycle) */
.ist-open{background:#fee2e2;color:#dc2626}
.ist-investigating{background:#dbeafe;color:#1d4ed8}
.ist-resolved{background:#d1fae5;color:#059669}
.ist-closed{background:#f1f5f9;color:#64748b}

/* Tables */
.s-table{width:100%;border-collapse:collapse;font-size:.82rem}
.s-table th{text-align:right;padding:.5rem .875rem;background:#f8fafc;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.s-table td{padding:.6rem .875rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.s-table tr:last-child td{border-bottom:none}
.s-table tr:hover td{background:#f8faff}

/* Meta items */
.meta-item{display:flex;flex-direction:column;gap:.2rem;padding:.6rem .875rem;background:#f8fafc;border-radius:.6rem}
.meta-label{font-size:.7rem;color:#9ca3af;font-weight:600}
.meta-value{font-size:.85rem;color:#1f2937;font-weight:500}

/* Response Trend */
.resp-wrap{display:flex;align-items:flex-end;gap:2px;height:64px}
.resp-bar{flex:1;border-radius:2px 2px 0 0;cursor:default;min-height:3px}
.resp-bar--ok{background:#10b981}
.resp-bar--warn{background:#f59e0b}
.resp-bar--bad{background:#ef4444}
</style>
@endpush

@section('content')

{{-- Breadcrumb --}}
<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;font-size:.82rem;color:#6b7280;flex-wrap:wrap">
    <a href="{{ route('admin.infrastructure.monitoring.index') }}" style="color:#4f46e5;text-decoration:none">مرکز مانیتورینگ</a>
    <span>›</span>
    <span style="color:#1f2937;font-weight:600">{{ $site->title }}</span>
    <span style="font-family:monospace;background:#f1f5f9;padding:.1rem .5rem;border-radius:.375rem;font-size:.78rem;color:#374151">{{ $site->domain }}</span>
    @if($latestLog)
        <span class="badge-s badge-s--{{ $latestLog->status }}">
            @if($latestLog->status === 'healthy') سالم @elseif($latestLog->status === 'warning') هشدار @elseif($latestLog->status === 'critical') بحرانی @else نامشخص @endif
        </span>
    @endif
</div>

{{-- Uptime Cards --}}
<div class="site-grid">
    @php
        $u24Color  = $uptime24h  >= 99 ? 'good' : ($uptime24h  >= 90 ? 'warn' : 'bad');
        $u7Color   = $uptime7d   >= 99 ? 'good' : ($uptime7d   >= 90 ? 'warn' : 'bad');
        $u30Color  = $uptime30d  >= 99 ? 'good' : ($uptime30d  >= 90 ? 'warn' : 'bad');
    @endphp
    <div class="uptime-card {{ $u24Color }}">
        <div class="uptime-pct">{{ \App\Helpers\NumberHelper::formatFa($uptime24h, 1) }}٪</div>
        <div class="uptime-label">آپتایم ۲۴ ساعت</div>
    </div>
    <div class="uptime-card {{ $u7Color }}">
        <div class="uptime-pct">{{ \App\Helpers\NumberHelper::formatFa($uptime7d, 1) }}٪</div>
        <div class="uptime-label">آپتایم ۷ روز</div>
    </div>
    <div class="uptime-card {{ $u30Color }}">
        <div class="uptime-pct">{{ \App\Helpers\NumberHelper::formatFa($uptime30d, 1) }}٪</div>
        <div class="uptime-label">آپتایم ۳۰ روز</div>
    </div>
</div>

{{-- Site Info + Uptime History --}}
<div class="site-grid-2">

    {{-- Current Status --}}
    <div class="s-card">
        <div class="s-card-head">
            <h3>
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                </svg>
                اطلاعات سایت
            </h3>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
            <div class="meta-item">
                <span class="meta-label">دامنه</span>
                <span class="meta-value" style="color:#4f46e5;font-family:monospace">{{ $site->domain }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">آخرین بررسی</span>
                <span class="meta-value">{{ $latestLog?->checked_at?->diffForHumans() ?? '—' }}</span>
            </div>
            @if($latestLog)
            <div class="meta-item">
                <span class="meta-label">زمان پاسخ</span>
                @php $ms = $latestLog->response_time_ms; @endphp
                <span class="meta-value {{ $ms > 2000 ? 'text-red-600' : ($ms > 800 ? 'text-amber-600' : 'text-green-600') }}">
                    {{ $ms ? \App\Helpers\NumberHelper::formatFa($ms) . 'ms' : '—' }}
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">HTTP Status</span>
                <span class="meta-value">{{ $latestLog->http_status ?? '—' }}</span>
            </div>
            @endif
            <div class="meta-item">
                <span class="meta-label">SSL</span>
                @php $ssl = $latestLog?->ssl_status ?? 'unknown'; @endphp
                <span class="meta-value">
                    <span class="badge-s badge-s--{{ $ssl === 'valid' ? 'healthy' : ($ssl === 'expiring_soon' ? 'warning' : ($ssl === 'invalid' || $ssl === 'critical' ? 'critical' : 'unknown')) }}">
                        @if($ssl === 'valid') معتبر
                        @elseif($ssl === 'expiring_soon') رو به انقضا
                        @elseif($ssl === 'invalid' || $ssl === 'critical') نامعتبر
                        @else نامشخص @endif
                    </span>
                    @if($latestLog?->ssl_days_remaining !== null)
                        <span style="font-size:.75rem;color:#6b7280;margin-right:.3rem">({{ \App\Helpers\NumberHelper::formatFa($latestLog->ssl_days_remaining) }}روز)</span>
                    @endif
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">انقضای دامنه</span>
                @php $dd = $latestLog?->domain_days_remaining; @endphp
                <span class="meta-value">
                    @if($dd !== null)
                        <span class="badge-s badge-s--{{ $dd < 7 ? 'critical' : ($dd < 14 ? 'warning' : 'healthy') }}">{{ \App\Helpers\NumberHelper::formatFa($dd) }}روز</span>
                    @else
                        @if($site->domain_expires_at)
                            {{ \App\Helpers\NumberHelper::toPersianNumbers($site->domain_expires_at->format('Y/m/d')) }}
                        @else
                            <span style="color:#94a3b8">—</span>
                        @endif
                    @endif
                </span>
            </div>
            @if($latestLog)
            <div class="meta-item">
                <span class="meta-label">دیتابیس</span>
                @php $db = $latestLog->db_status ?? 'unknown'; @endphp
                <span class="meta-value">
                    <span class="badge-s badge-s--{{ $db === 'ok' ? 'healthy' : ($db === 'error' ? 'critical' : 'unknown') }}">
                        @if($db === 'ok') متصل @elseif($db === 'error') خطا @else نامشخص @endif
                    </span>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Redis</span>
                @php $rd = $latestLog->redis_status ?? 'unknown'; @endphp
                <span class="meta-value">
                    <span class="badge-s badge-s--{{ $rd === 'ok' ? 'healthy' : ($rd === 'error' ? 'critical' : 'unknown') }}">
                        @if($rd === 'ok') متصل @elseif($rd === 'error') خطا @else نامشخص @endif
                    </span>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Queue</span>
                @php $q = $latestLog->queue_status ?? 'unknown'; @endphp
                <span class="meta-value">
                    <span class="badge-s badge-s--{{ $q === 'ok' ? 'healthy' : ($q === 'idle' ? 'warning' : ($q === 'error' ? 'critical' : 'unknown')) }}">
                        @if($q === 'ok') فعال @elseif($q === 'idle') خاموش @elseif($q === 'error') خطا @else نامشخص @endif
                    </span>
                </span>
            </div>
            @if($latestLog->failed_jobs !== null)
            <div class="meta-item">
                <span class="meta-label">Failed Jobs</span>
                <span class="meta-value {{ $latestLog->failed_jobs > 0 ? 'text-red-600 font-bold' : 'text-green-600' }}">
                    {{ \App\Helpers\NumberHelper::formatFa($latestLog->failed_jobs) }}
                </span>
            </div>
            @endif
            @if($latestLog->disk_used_percent !== null)
            <div class="meta-item">
                <span class="meta-label">دیسک</span>
                @php $dsk = $latestLog->disk_used_percent; @endphp
                <span class="meta-value {{ $dsk >= 90 ? 'text-red-600 font-bold' : ($dsk >= 75 ? 'text-amber-600' : 'text-green-600') }}">
                    {{ \App\Helpers\NumberHelper::formatFa($dsk) }}٪
                </span>
            </div>
            @endif
            @if($latestLog->memory_used_percent !== null)
            <div class="meta-item">
                <span class="meta-label">حافظه RAM</span>
                @php $mem = $latestLog->memory_used_percent; @endphp
                <span class="meta-value {{ $mem >= 90 ? 'text-red-600 font-bold' : ($mem >= 75 ? 'text-amber-600' : 'text-green-600') }}">
                    {{ \App\Helpers\NumberHelper::formatFa($mem) }}٪
                </span>
            </div>
            @endif
            @endif
        </div>
    </div>

    {{-- Uptime History (14 days) --}}
    <div class="s-card">
        <div class="s-card-head">
            <h3>
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                آپتایم ۱۴ روز اخیر
            </h3>
        </div>
        @if(empty($uptimeHistory))
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">داده کافی موجود نیست</p>
            </div>
        @else
            @php $maxPct = max(array_column($uptimeHistory, 'uptime_percent') ?: [1]); @endphp
            <div class="uptime-hist">
                @foreach($uptimeHistory as $day)
                    @php
                        $pct = $day['uptime_percent'];
                        $h   = max(4, (int)($pct / 100 * 44));
                        $cls = $pct >= 99 ? 'healthy' : ($pct >= 90 ? 'warning' : 'critical');
                    @endphp
                    <div class="uptime-bar uptime-bar--{{ $cls }}"
                         style="height:{{ $h }}px"
                         title="{{ $day['date'] }}: {{ number_format($pct, 1) }}%"></div>
                @endforeach
            </div>
            <div class="flex justify-between text-xs text-gray-400 mt-1 px-1">
                <span>{{ $uptimeHistory[0]['date'] ?? '' }}</span>
                <span>{{ end($uptimeHistory)['date'] ?? '' }}</span>
            </div>
            <div class="mt-3 flex gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-green-400 inline-block"></span> ≥99%</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-400 inline-block"></span> ≥90%</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-red-400 inline-block"></span> &lt;90%</span>
            </div>
        @endif

        {{-- Response Time Trend --}}
        <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f1f5f9">
            <div style="font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.6rem">روند زمان پاسخ (۲۴ ساعت)</div>
            @if($responseTrend->isEmpty())
                <p class="text-xs text-gray-400 text-center py-2">داده‌ای موجود نیست</p>
            @else
                @php $maxMs = $responseTrend->max('response_time_ms') ?: 1; @endphp
                <div class="resp-wrap">
                    @foreach($responseTrend as $point)
                        @php
                            $ms2 = $point->response_time_ms ?? 0;
                            $h2  = max(3, (int)($ms2 / $maxMs * 60));
                            $rc  = $ms2 > 2000 ? 'bad' : ($ms2 > 800 ? 'warn' : 'ok');
                        @endphp
                        <div class="resp-bar resp-bar--{{ $rc }}"
                             style="height:{{ $h2 }}px"
                             title="{{ $point->checked_at?->format('H:i') }} — {{ number_format($ms2) }}ms"></div>
                    @endforeach
                </div>
                <div class="flex justify-between text-xs text-gray-400 mt-1 px-1">
                    <span>{{ $responseTrend->first()->checked_at?->format('H:i') }}</span>
                    <span>{{ $responseTrend->last()->checked_at?->format('H:i') }}</span>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Incidents --}}
<div class="s-card" style="margin-bottom:1.5rem">
    <div class="s-card-head">
        <h3>
            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            حوادث ({{ \App\Helpers\NumberHelper::formatFa($incidents->count()) }})
        </h3>
        <a href="{{ route('admin.incidents.index', ['site_id' => $site->id]) }}" style="font-size:.78rem;color:#4f46e5;text-decoration:none">همه حوادث ↗</a>
    </div>
    @if($incidents->isEmpty())
        <div class="text-center py-8 text-gray-400">
            <svg class="w-8 h-8 mx-auto mb-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-green-600 font-medium">هیچ حادثه‌ای ثبت نشده</p>
        </div>
    @else
        <table class="s-table">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>شدت</th>
                    <th>وضعیت</th>
                    <th>نوع</th>
                    <th>ایجاد</th>
                    <th>مسئول</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($incidents as $inc)
                <tr>
                    <td style="font-weight:600;color:#1f2937;max-width:240px">{{ Str::limit($inc->title, 50) }}</td>
                    <td><span class="sev-badge sev-{{ $inc->severity }}">{{ $inc->severityLabel() }}</span></td>
                    <td>
                        <span class="badge-s ist-{{ $inc->status }}">{{ $inc->statusLabel() }}</span>
                    </td>
                    <td style="font-size:.75rem;color:#6b7280;font-family:monospace">{{ $inc->type }}</td>
                    <td style="font-size:.75rem;color:#6b7280">{{ $inc->created_at->diffForHumans() }}</td>
                    <td style="font-size:.75rem;color:#374151">{{ $inc->assignedTo?->name ?? '—' }}</td>
                    <td>
                        <a href="{{ route('admin.incidents.show', $inc->id) }}" style="color:#4f46e5;font-size:.75rem;text-decoration:none">جزئیات ↗</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Health History + Backup Runs --}}
<div class="site-grid-2">

    {{-- Health Check History --}}
    <div class="s-card">
        <div class="s-card-head">
            <h3>
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                تاریخچه بررسی سلامت (۴۸ مورد اخیر)
            </h3>
        </div>
        @if($healthHistory->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">هنوز داده‌ای ثبت نشده</p>
        @else
        <div style="overflow-x:auto">
        <table class="s-table">
            <thead>
                <tr>
                    <th>زمان</th>
                    <th>وضعیت</th>
                    <th>زمان پاسخ</th>
                    <th>SSL</th>
                    <th>Queue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($healthHistory as $h)
                <tr>
                    <td style="font-size:.73rem;color:#6b7280;white-space:nowrap">{{ $h->checked_at?->format('m/d H:i') }}</td>
                    <td>
                        <span class="badge-s badge-s--{{ $h->status }}">
                            @if($h->status === 'healthy') سالم @elseif($h->status === 'warning') هشدار @elseif($h->status === 'critical') بحرانی @else نامشخص @endif
                        </span>
                    </td>
                    <td>
                        @if($h->response_time_ms !== null)
                            @php $rms = $h->response_time_ms; @endphp
                            <span class="font-mono text-xs {{ $rms > 2000 ? 'text-red-600 font-bold' : ($rms > 800 ? 'text-amber-600' : 'text-green-600') }}">
                                {{ number_format($rms) }}ms
                            </span>
                        @else —
                        @endif
                    </td>
                    <td>
                        @php $hs = $h->ssl_status ?? 'unknown'; @endphp
                        <span class="badge-s badge-s--{{ $hs === 'valid' ? 'healthy' : ($hs === 'expiring_soon' ? 'warning' : ($hs === 'invalid' || $hs === 'critical' ? 'critical' : 'unknown')) }}" style="font-size:.65rem">
                            @if($hs === 'valid') ✓ @elseif($hs === 'expiring_soon') ⚠ @elseif($hs === 'invalid' || $hs === 'critical') ✗ @else ? @endif
                        </span>
                    </td>
                    <td>
                        @php $hq = $h->queue_status ?? 'unknown'; @endphp
                        <span class="badge-s badge-s--{{ $hq === 'ok' ? 'healthy' : ($hq === 'idle' ? 'warning' : ($hq === 'error' ? 'critical' : 'unknown')) }}" style="font-size:.65rem">
                            @if($hq === 'ok') ✓ @elseif($hq === 'idle') ⚠ @elseif($hq === 'error') ✗ @else ? @endif
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- Backup History --}}
    <div class="s-card">
        <div class="s-card-head">
            <h3>
                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
                </svg>
                تاریخچه پشتیبان‌گیری
            </h3>
        </div>
        @if($backups->isEmpty())
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">پشتیبانی برای این سایت ثبت نشده</p>
            </div>
        @else
            <table class="s-table">
                <thead>
                    <tr>
                        <th>نوع</th>
                        <th>وضعیت</th>
                        <th>حجم</th>
                        <th>مدت</th>
                        <th>زمان</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backups as $bk)
                    <tr>
                        <td style="font-size:.78rem;color:#374151;font-family:monospace">{{ $bk->backup_type }}</td>
                        <td>
                            <span class="badge-s badge-s--{{ $bk->status === 'success' ? 'healthy' : 'critical' }}">
                                {{ $bk->status === 'success' ? 'موفق' : 'ناموفق' }}
                            </span>
                        </td>
                        <td style="font-size:.78rem;color:#6b7280">
                            {{ $bk->file_size_mb ? \App\Helpers\NumberHelper::formatFa($bk->file_size_mb, 1) . ' MB' : '—' }}
                        </td>
                        <td style="font-size:.78rem;color:#6b7280">
                            {{ $bk->duration_seconds ? \App\Helpers\NumberHelper::formatFa($bk->duration_seconds) . 'ث' : '—' }}
                        </td>
                        <td style="font-size:.73rem;color:#6b7280;white-space:nowrap">
                            {{ $bk->started_at?->diffForHumans() }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>

@endsection
