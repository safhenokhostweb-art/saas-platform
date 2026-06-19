@extends('layouts.admin')

@section('header', 'مرکز اطلاع‌رسانی')

@push('head')
<style>
/* ─── Layout ─── */
.nc-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem}
@media(max-width:1100px){.nc-grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.nc-grid-4{grid-template-columns:1fr}}

/* ─── Stat Cards ─── */
.nc-stat{border-radius:1rem;padding:1.25rem 1.5rem;color:#fff;display:block;transition:all .25s;border:1px solid rgba(255,255,255,.1)}
.nc-stat:hover{transform:translateY(-3px);box-shadow:0 16px 40px rgba(15,23,42,.18)}
.nc-stat--indigo{background:linear-gradient(135deg,#4f46e5,#2e1065)}
.nc-stat--red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.nc-stat--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.nc-stat--green{background:linear-gradient(135deg,#059669,#064e3b)}
.nc-stat__label{font-size:.78rem;font-weight:500;opacity:.85;margin-bottom:.3rem}
.nc-stat__value{font-size:2.1rem;font-weight:800;line-height:1}
.nc-stat__sub{font-size:.72rem;opacity:.7;margin-top:.3rem}

/* ─── Filter bar ─── */
.nc-filter{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem 1.25rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-bottom:1.25rem;box-shadow:0 2px 8px rgba(15,23,42,.04)}
.nc-filter select,.nc-filter input[type=text]{border:1px solid #e2e8f0;border-radius:.5rem;padding:.4rem .75rem;font-size:.82rem;color:#374151;background:#f8fafc;outline:none}
.nc-filter select:focus,.nc-filter input:focus{border-color:#6366f1;background:#fff}

/* ─── Notification Row ─── */
.nc-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,.04);overflow:hidden}
.nc-row{display:flex;align-items:flex-start;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;border-right:4px solid transparent;transition:background .15s}
.nc-row:last-child{border-bottom:none}
.nc-row:hover{background:#f8faff}
.nc-row--unread{background:#fafbff}
.nc-row--critical{border-right-color:#dc2626}
.nc-row--high{border-right-color:#ea580c}
.nc-row--medium{border-right-color:#d97706}
.nc-row--low{border-right-color:#2563eb}

/* ─── Type Icon ─── */
.nc-icon{width:2.5rem;height:2.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem}
.nc-icon--critical{background:#fee2e2;color:#dc2626}
.nc-icon--high{background:#ffedd5;color:#ea580c}
.nc-icon--medium{background:#fef3c7;color:#d97706}
.nc-icon--low{background:#eff6ff;color:#2563eb}

/* ─── Badges ─── */
.nc-badge{display:inline-flex;align-items:center;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:9999px;gap:.2rem}
.nc-badge--critical{background:#fee2e2;color:#991b1b}
.nc-badge--high{background:#ffedd5;color:#9a3412}
.nc-badge--medium{background:#fef3c7;color:#92400e}
.nc-badge--low{background:#eff6ff;color:#1e40af}
.nc-badge--sms{background:#f0fdf4;color:#166534}
.nc-badge--both{background:#f0fdf4;color:#166534}
.nc-badge--in_app{background:#f5f3ff;color:#5b21b6}
.nc-badge--silent{background:#f1f5f9;color:#64748b}

/* ─── Trend bar ─── */
.trend-wrap{display:flex;align-items:flex-end;gap:4px;height:48px}
.trend-bar{flex:1;border-radius:3px 3px 0 0;background:#6366f1;opacity:.7;min-height:3px;transition:all .2s}

/* ─── Actions ─── */
.nc-action-btn{width:1.75rem;height:1.75rem;border-radius:.5rem;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;transition:all .15s;flex-shrink:0}
.nc-action-btn:hover{background:#f5f3ff;border-color:#6366f1;color:#4f46e5}
.nc-action-btn--danger:hover{background:#fee2e2;border-color:#ef4444;color:#dc2626}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">مرکز اطلاع‌رسانی</h1>
        <p class="text-gray-500 text-sm mt-1">مدیریت یکپارچه تمام اعلان‌های پلتفرم</p>
    </div>
    <div class="flex gap-2">
        <button onclick="markAllRead()" class="btn btn-secondary text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            همه خوانده شد
        </button>
        <a href="{{ route('admin.notification-center.create') }}" class="btn btn-primary text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            ارسال اعلان
        </a>
    </div>
</div>


{{-- ─── Stats ─── --}}
<div class="nc-grid-4 mb-6">
    <div class="nc-stat nc-stat--indigo">
        <div class="nc-stat__label">کل امروز</div>
        <div class="nc-stat__value">{{ number_format($stats['total_today']) }}</div>
        <div class="nc-stat__sub">{{ number_format($stats['total_all_time']) }} در کل</div>
    </div>
    <div class="nc-stat {{ $stats['unread'] > 0 ? 'nc-stat--amber' : 'nc-stat--green' }}">
        <div class="nc-stat__label">خوانده‌نشده</div>
        <div class="nc-stat__value">{{ number_format($stats['unread']) }}</div>
        <div class="nc-stat__sub">نیازمند توجه</div>
    </div>
    <div class="nc-stat {{ $stats['critical_open'] > 0 ? 'nc-stat--red' : 'nc-stat--green' }}">
        <div class="nc-stat__label">بحرانی باز</div>
        <div class="nc-stat__value">{{ number_format($stats['critical_open']) }}</div>
        <div class="nc-stat__sub">اولویت فوری</div>
    </div>
    <div class="nc-stat nc-stat--green">
        <div class="nc-stat__label">پیامک امروز</div>
        <div class="nc-stat__value">{{ number_format($stats['sms_sent_today']) }}</div>
        <div class="nc-stat__sub">SMS ارسال‌شده</div>
    </div>
</div>

{{-- ─── Trend + By Type ─── --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.25rem">

    {{-- Daily Trend --}}
    <div class="nc-card" style="padding:1.25rem 1.5rem">
        <div class="font-bold text-gray-800 text-sm mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            اعلان‌ها — ۷ روز اخیر
        </div>
        @if($stats['daily_trend']->isEmpty())
            <p class="text-sm text-gray-400 text-center py-3">هنوز داده‌ای ثبت نشده</p>
        @else
            @php $maxT = $stats['daily_trend']->max('total') ?: 1; @endphp
            <div class="trend-wrap">
                @foreach($stats['daily_trend'] as $d)
                    @php $h = max(3, (int)($d->total / $maxT * 44)); @endphp
                    <div class="trend-bar" style="height:{{ $h }}px" title="{{ $d->day }} — {{ $d->total }}"></div>
                @endforeach
            </div>
            <div class="flex justify-between text-xs text-gray-400 mt-1">
                <span>{{ $stats['daily_trend']->first()->day ?? '' }}</span>
                <span>{{ $stats['daily_trend']->last()->day ?? '' }}</span>
            </div>
        @endif
    </div>

    {{-- By Priority --}}
    <div class="nc-card" style="padding:1.25rem 1.5rem">
        <div class="font-bold text-gray-800 text-sm mb-3">اعلان‌ها بر اساس اولویت</div>
        @php
        $pMap = ['critical'=>'بحرانی','high'=>'بالا','medium'=>'متوسط','low'=>'پایین'];
        $pColors = ['critical'=>'#dc2626','high'=>'#ea580c','medium'=>'#d97706','low'=>'#2563eb'];
        $byP = $stats['by_priority'];
        $maxP = $byP->max() ?: 1;
        @endphp
        @foreach($pMap as $k => $label)
            @php $val = $byP[$k] ?? 0; $w = $maxP > 0 ? (int)($val / $maxP * 100) : 0; @endphp
            <div class="mb-2">
                <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                    <span>{{ $label }}</span><span class="font-bold">{{ $val }}</span>
                </div>
                <div style="height:6px;background:#f1f5f9;border-radius:9999px;overflow:hidden">
                    <div style="height:100%;width:{{ $w }}%;background:{{ $pColors[$k] }};border-radius:9999px;transition:width .4s"></div>
                </div>
            </div>
        @endforeach
    </div>

</div>

{{-- ─── Filter bar ─── --}}
<form method="GET" action="{{ route('admin.notification-center.index') }}" class="nc-filter">
    <select name="type" onchange="this.form.submit()">
        <option value="">همه انواع</option>
        @foreach(\App\Models\NotificationCenterLog::TYPES as $val => $label)
            <option value="{{ $val }}" {{ request('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <select name="priority" onchange="this.form.submit()">
        <option value="">همه اولویت‌ها</option>
        @foreach(\App\Models\NotificationCenterLog::PRIORITIES as $val => $label)
            <option value="{{ $val }}" {{ request('priority') === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <select name="audience" onchange="this.form.submit()">
        <option value="">همه مخاطبان</option>
        @foreach(\App\Models\NotificationCenterLog::AUDIENCES as $val => $label)
            <option value="{{ $val }}" {{ request('audience') === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <select name="status" onchange="this.form.submit()">
        <option value="">همه وضعیت‌ها</option>
        <option value="unread" {{ request('status') === 'unread' ? 'selected' : '' }}>خوانده‌نشده</option>
        <option value="read"   {{ request('status') === 'read'   ? 'selected' : '' }}>خوانده‌شده</option>
    </select>
    <select name="period" onchange="this.form.submit()">
        <option value="">همه زمان‌ها</option>
        <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>امروز</option>
        <option value="week"  {{ request('period') === 'week'  ? 'selected' : '' }}>این هفته</option>
        <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>این ماه</option>
    </select>
    <input type="text" name="search" placeholder="جستجوی عنوان..." value="{{ request('search') }}"
           style="flex:1;min-width:180px" onkeydown="if(event.key==='Enter')this.form.submit()">
    @if(request()->hasAny(['type','priority','audience','status','period','search']))
        <a href="{{ route('admin.notification-center.index') }}"
           class="text-sm text-indigo-600 hover:underline whitespace-nowrap">× پاک کردن</a>
    @endif
</form>

{{-- ─── Notification List ─── --}}
<div class="nc-card">
    @if($logs->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="font-medium text-lg">هیچ اعلانی یافت نشد</p>
            <p class="text-sm mt-1">
                @if(request()->hasAny(['type','priority','audience','status','period','search']))
                    فیلترها را تغییر دهید یا
                    <a href="{{ route('admin.notification-center.index') }}" class="text-indigo-600 hover:underline">همه را ببینید</a>
                @else
                    اولین اعلان را
                    <a href="{{ route('admin.notification-center.create') }}" class="text-indigo-600 hover:underline">ارسال کنید</a>
                @endif
            </p>
        </div>
    @else
        @foreach($logs as $log)
        <div class="nc-row nc-row--{{ $log->isUnread() ? 'unread ' : '' }}nc-row--{{ $log->priority }}"
             id="nc-row-{{ $log->id }}">

            {{-- Type Icon --}}
            <div class="nc-icon nc-icon--{{ $log->priority }}" title="{{ $log->typeFa() }}">
                @switch($log->type)
                    @case('site_down')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                        @break
                    @case('site_recovery')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                        @break
                    @case('infrastructure')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                        @break
                    @case('purchase')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        @break
                    @case('renewal')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        @break
                    @case('support_ticket')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        @break
                    @case('deploy')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        @break
                    @default
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                @endswitch
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-start gap-2 flex-wrap">
                    <span class="font-bold text-sm text-gray-900 {{ $log->isUnread() ? 'font-extrabold' : '' }}">
                        @if($log->link)
                            <a href="{{ $log->link }}" class="hover:text-indigo-600">{{ $log->title }}</a>
                        @else
                            {{ $log->title }}
                        @endif
                    </span>
                    @if($log->isUnread())
                        <span style="width:.45rem;height:.45rem;background:#6366f1;border-radius:50%;display:inline-block;margin-top:.35rem;flex-shrink:0"></span>
                    @endif
                </div>
                @if($log->message)
                    <p class="text-xs text-gray-500 mt-0.5 truncate max-w-xl">{{ $log->message }}</p>
                @endif
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span class="nc-badge nc-badge--{{ $log->priority }}">{{ $log->priorityFa() }}</span>
                    <span class="nc-badge" style="background:#f1f5f9;color:#475569">{{ $log->typeFa() }}</span>
                    <span class="nc-badge nc-badge--{{ $log->channel }}">
                        @if($log->channel === 'sms' || $log->channel === 'both')📱 @endif
                        {{ $log->channelFa() }}
                    </span>
                    @if($log->site)
                        <span class="text-xs text-indigo-500">{{ $log->site->title }}</span>
                    @endif
                    @if($log->sms_sent_at)
                        <span class="nc-badge nc-badge--sms">SMS ✓</span>
                    @endif
                    <span class="text-xs text-gray-400">{{ \App\Helpers\Jalali::diffForHumansFa($log->created_at) }}</span>
                    @if($log->creator)
                        <span class="text-xs text-gray-400">· توسط {{ $log->creator->name ?? 'سیستم' }}</span>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1 flex-shrink-0">
                @if($log->isUnread())
                <button onclick="markRead({{ $log->id }}, this)"
                        class="nc-action-btn" title="علامت‌گذاری به عنوان خوانده‌شده">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>
                @endif
                <button onclick="deleteNotif({{ $log->id }}, this)"
                        class="nc-action-btn nc-action-btn--danger" title="حذف">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        @endforeach

        {{-- Pagination --}}
        @if($logs->hasPages())
        <div class="p-4 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
        @endif
    @endif
</div>

{{-- Bottom quick links --}}
<div style="display:flex;gap:1rem;margin-top:1.25rem;flex-wrap:wrap">
    <a href="{{ route('admin.notification-center.stats') }}" target="_blank"
       class="text-sm text-indigo-600 hover:underline flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        JSON Stats API
    </a>
    <a href="{{ route('admin.infrastructure.monitoring.index') }}"
       class="text-sm text-gray-500 hover:text-indigo-600 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
        مرکز مانیتورینگ
    </a>
    <a href="{{ route('admin.system-alerts.index') }}"
       class="text-sm text-gray-500 hover:text-indigo-600 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        هشدارهای سیستمی (Bell)
    </a>
</div>

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

function markRead(id, btn) {
    fetch(`/admin/notification-center/${id}/read`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
    }).then(r => r.json()).then(() => {
        const row = document.getElementById('nc-row-' + id);
        if (row) {
            row.classList.remove('nc-row--unread');
            if (btn) btn.remove();
        }
    });
}

function deleteNotif(id, btn) {
    if (!confirm('این اعلان حذف شود؟')) return;
    fetch(`/admin/notification-center/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
    }).then(r => r.json()).then(() => {
        const row = document.getElementById('nc-row-' + id);
        if (row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 250); }
    });
}

function markAllRead() {
    fetch('/admin/notification-center/read-all', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
    }).then(r => r.json()).then(d => {
        document.querySelectorAll('.nc-row--unread').forEach(el => el.classList.remove('nc-row--unread'));
        document.querySelectorAll('[onclick^="markRead"]').forEach(el => el.remove());
    });
}
</script>
@endpush

@endsection
