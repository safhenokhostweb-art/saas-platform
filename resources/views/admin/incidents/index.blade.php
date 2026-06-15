@extends('layouts.admin')

@section('header', 'مدیریت حوادث')

@push('head')
<style>
.inc-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:1.75rem}
@media(max-width:900px){.inc-grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.inc-grid-4{grid-template-columns:1fr}}

.stat-card{border-radius:1rem;padding:1.25rem 1.5rem;color:#fff;border:1px solid rgba(255,255,255,.12)}
.stat-card--red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.stat-card--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.stat-card--violet{background:linear-gradient(135deg,#7c3aed,#2e1065)}
.stat-card--green{background:linear-gradient(135deg,#059669,#064e3b)}
.stat-card__val{font-size:2.2rem;font-weight:800;line-height:1}
.stat-card__label{font-size:.8rem;opacity:.85;margin-top:.3rem}

.filter-bar{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center}
.filter-bar select,.filter-bar input{height:2.25rem;padding:0 .75rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.82rem;background:#f9fafb;font-family:inherit}
.filter-bar .btn-filter{height:2.25rem;padding:0 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;cursor:pointer;font-family:inherit}
.filter-bar .btn-reset{height:2.25rem;padding:0 1rem;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.82rem;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center}

.inc-table{width:100%;border-collapse:collapse;font-size:.82rem}
.inc-table th{text-align:right;padding:.6rem 1rem;background:#f8fafc;color:#64748b;font-weight:600;border-bottom:2px solid #e2e8f0;white-space:nowrap}
.inc-table td{padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.inc-table tr:hover td{background:#fafbff}

.sev-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem;font-weight:700;padding:.2rem .65rem;border-radius:9999px}
.sev-critical{background:#fee2e2;color:#991b1b}
.sev-high{background:#ffedd5;color:#9a3412}
.sev-medium{background:#fef3c7;color:#92400e}
.sev-low{background:#d1fae5;color:#065f46}

.status-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem;font-weight:600;padding:.2rem .6rem;border-radius:9999px}
.st-open{background:#fee2e2;color:#dc2626}
.st-investigating{background:#dbeafe;color:#1d4ed8}
.st-resolved{background:#d1fae5;color:#059669}
.st-closed{background:#f1f5f9;color:#64748b}

.inc-actions{display:flex;gap:.4rem;flex-wrap:wrap}
.btn-act{font-size:.72rem;padding:.2rem .65rem;border-radius:.4rem;border:none;cursor:pointer;font-family:inherit;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.25rem;white-space:nowrap}
.btn-investigate{background:#dbeafe;color:#1e40af}
.btn-resolve{background:#d1fae5;color:#065f46}
.btn-close{background:#f1f5f9;color:#475569}
.btn-view{background:#ede9fe;color:#5b21b6}

.card-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.04)}
</style>
@endpush

@section('content')

{{-- Stats --}}
<div class="inc-grid-4">
    <div class="stat-card stat-card--red">
        <div class="stat-card__val">{{ $stats['open'] }}</div>
        <div class="stat-card__label">باز</div>
    </div>
    <div class="stat-card stat-card--violet">
        <div class="stat-card__val">{{ $stats['investigating'] }}</div>
        <div class="stat-card__label">در حال بررسی</div>
    </div>
    <div class="stat-card stat-card--amber">
        <div class="stat-card__val">{{ $stats['critical'] }}</div>
        <div class="stat-card__label">بحرانی فعال</div>
    </div>
    <div class="stat-card stat-card--green">
        <div class="stat-card__val">{{ $stats['resolved_today'] }}</div>
        <div class="stat-card__label">برطرف شده امروز</div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="filter-bar">
    <select name="status">
        <option value="" {{ request('status') === '' ? 'selected' : '' }}>وضعیت: فعال</option>
        <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>همه</option>
        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>باز</option>
        <option value="investigating" {{ request('status') === 'investigating' ? 'selected' : '' }}>در حال بررسی</option>
        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>برطرف شده</option>
        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>بسته</option>
    </select>
    <select name="severity">
        <option value="">همه شدت‌ها</option>
        <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>بحرانی</option>
        <option value="high" {{ request('severity') === 'high' ? 'selected' : '' }}>بالا</option>
        <option value="medium" {{ request('severity') === 'medium' ? 'selected' : '' }}>متوسط</option>
        <option value="low" {{ request('severity') === 'low' ? 'selected' : '' }}>پایین</option>
    </select>
    <select name="site_id">
        <option value="">همه سایت‌ها</option>
        <option value="0" {{ request('site_id') === '0' ? 'selected' : '' }}>پلتفرم (بدون سایت)</option>
        @foreach($sites as $s)
            <option value="{{ $s->id }}" {{ request('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
        @endforeach
    </select>
    <select name="type">
        <option value="">همه انواع</option>
        <option value="site_down">سایت از دسترس</option>
        <option value="db_error">خطای دیتابیس</option>
        <option value="redis_error">خطای Redis</option>
        <option value="ssl_expiring">SSL انقضا</option>
        <option value="domain_expiring">دامنه انقضا</option>
        <option value="queue_dead">صف متوقف</option>
        <option value="disk_critical">دیسک پر</option>
        <option value="backup_failure">خرابی بکاپ</option>
        <option value="scheduler_failure">خرابی زمان‌بند</option>
        <option value="sms_failure">خرابی SMS</option>
        <option value="ai_failure">خرابی AI</option>
        <option value="deploy_failure">خطای استقرار</option>
    </select>
    <button type="submit" class="btn-filter">اعمال فیلتر</button>
    <a href="{{ route('admin.incidents.index') }}" class="btn-reset">پاک کردن</a>
</form>

@if(session('success'))
<div style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:.75rem;padding:.75rem 1.25rem;margin-bottom:1rem;font-size:.875rem;">
    {{ session('success') }}
</div>
@endif

{{-- Table --}}
<div class="card-wrap">
    <table class="inc-table">
        <thead>
            <tr>
                <th>#</th>
                <th>عنوان</th>
                <th>شدت</th>
                <th>وضعیت</th>
                <th>سایت</th>
                <th>نوع</th>
                <th>مسئول</th>
                <th>ایجاد</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($incidents as $inc)
            <tr>
                <td style="color:#94a3b8;font-size:.75rem;">{{ $inc->id }}</td>
                <td>
                    <a href="{{ route('admin.incidents.show', $inc->id) }}" style="font-weight:600;color:#1f2937;text-decoration:none;">
                        {{ Str::limit($inc->title, 55) }}
                    </a>
                    @if($inc->support_ticket_id)
                        <span style="font-size:.68rem;color:#6366f1;margin-right:.4rem;">تیکت #{{ $inc->support_ticket_id }}</span>
                    @endif
                </td>
                <td>
                    <span class="sev-badge sev-{{ $inc->severity }}">{{ $inc->severityLabel() }}</span>
                </td>
                <td>
                    <span class="status-badge st-{{ $inc->status }}">{{ $inc->statusLabel() }}</span>
                </td>
                <td style="font-size:.78rem;color:#4f46e5;">
                    {{ $inc->site?->domain ?? 'پلتفرم' }}
                </td>
                <td style="font-size:.75rem;color:#6b7280;">{{ $inc->typeLabel() }}</td>
                <td style="font-size:.75rem;color:#374151;">{{ $inc->assignedTo?->name ?? '—' }}</td>
                <td style="font-size:.72rem;color:#9ca3af;white-space:nowrap;">
                    {{ \App\Helpers\NumberHelper::formatFa($inc->created_at->format('Y/m/d')) }}
                </td>
                <td>
                    <div class="inc-actions">
                        <a href="{{ route('admin.incidents.show', $inc->id) }}" class="btn-act btn-view">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            جزئیات
                        </a>
                        @if($inc->isOpen())
                        <form method="POST" action="{{ route('admin.incidents.investigate', $inc->id) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn-act btn-investigate">بررسی</button>
                        </form>
                        @endif
                        @if($inc->isActive())
                        <form method="POST" action="{{ route('admin.incidents.resolve', $inc->id) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn-act btn-resolve">برطرف شد</button>
                        </form>
                        @endif
                        @if($inc->isResolved())
                        <form method="POST" action="{{ route('admin.incidents.close', $inc->id) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn-act btn-close">بستن</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="text-align:center;padding:2rem;color:#9ca3af;">
                    هیچ حادثه‌ای یافت نشد.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($incidents->hasPages())
    <div style="padding:1rem 1.5rem;border-top:1px solid #f1f5f9;">
        {{ $incidents->links() }}
    </div>
    @endif
</div>
@endsection
