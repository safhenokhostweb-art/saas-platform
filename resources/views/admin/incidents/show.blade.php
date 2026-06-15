@extends('layouts.admin')

@section('header', 'جزئیات حادثه #' . $incident->id)

@push('head')
<style>
.inc-detail-grid{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem}
@media(max-width:900px){.inc-detail-grid{grid-template-columns:1fr}}

.card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.04);margin-bottom:1.5rem}
.card-head{padding:.875rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.card-head h3{font-size:.9rem;font-weight:700;color:#1f2937;margin:0}
.card-body{padding:1.25rem}

.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.meta-item{display:flex;flex-direction:column;gap:.2rem}
.meta-label{font-size:.72rem;color:#9ca3af;font-weight:600;text-transform:uppercase}
.meta-value{font-size:.875rem;color:#1f2937;font-weight:500}

.sev-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;font-weight:700;padding:.25rem .75rem;border-radius:9999px}
.sev-critical{background:#fee2e2;color:#991b1b}
.sev-high{background:#ffedd5;color:#9a3412}
.sev-medium{background:#fef3c7;color:#92400e}
.sev-low{background:#d1fae5;color:#065f46}

.status-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;font-weight:600;padding:.25rem .7rem;border-radius:9999px}
.st-open{background:#fee2e2;color:#dc2626}
.st-investigating{background:#dbeafe;color:#1d4ed8}
.st-resolved{background:#d1fae5;color:#059669}
.st-closed{background:#f1f5f9;color:#64748b}

/* Timeline */
.timeline{list-style:none;padding:0;margin:0;position:relative}
.timeline::before{content:'';position:absolute;right:1.1rem;top:0;bottom:0;width:2px;background:#e5e7eb}
.tl-item{display:flex;gap:1rem;padding:.5rem 0 1rem;position:relative}
.tl-dot{width:1.4rem;height:1.4rem;border-radius:50%;background:#fff;border:2px solid #e5e7eb;flex-shrink:0;display:flex;align-items:center;justify-content:center;z-index:1;margin-top:.15rem}
.tl-dot--system{border-color:#94a3b8;background:#f8fafc}
.tl-dot--user{border-color:#6366f1;background:#ede9fe}
.tl-content{flex:1}
.tl-note{font-size:.82rem;color:#374151;line-height:1.5}
.tl-meta{font-size:.72rem;color:#9ca3af;margin-top:.2rem}

/* Actions sidebar */
.action-btn{width:100%;padding:.65rem 1rem;border-radius:.6rem;border:none;cursor:pointer;font-family:inherit;font-size:.82rem;font-weight:600;margin-bottom:.6rem;text-align:right;display:block;transition:all .2s}
.action-btn:last-child{margin-bottom:0}
.btn-investigate{background:#dbeafe;color:#1d4ed8}
.btn-investigate:hover{background:#bfdbfe}
.btn-resolve{background:#d1fae5;color:#065f46}
.btn-resolve:hover{background:#a7f3d0}
.btn-close-inc{background:#f1f5f9;color:#475569}
.btn-close-inc:hover{background:#e2e8f0}

.note-form textarea{width:100%;border:1px solid #d1d5db;border-radius:.5rem;padding:.6rem .75rem;font-family:inherit;font-size:.82rem;resize:vertical;min-height:80px}
.note-form button{margin-top:.5rem;padding:.5rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;cursor:pointer;font-family:inherit;font-size:.82rem;font-weight:600}

.alert-box{padding:.75rem 1rem;border-radius:.75rem;font-size:.82rem;margin-bottom:1rem}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
</style>
@endpush

@section('content')

@if(session('success'))
<div class="alert-box alert-success">{{ session('success') }}</div>
@endif

{{-- Header --}}
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <a href="{{ route('admin.incidents.index') }}" style="color:#6b7280;font-size:.82rem;text-decoration:none;">← بازگشت به لیست</a>
    <span class="sev-badge sev-{{ $incident->severity }}">{{ $incident->severityLabel() }}</span>
    <span class="status-badge st-{{ $incident->status }}">{{ $incident->statusLabel() }}</span>
    @if($incident->customer_notified_at)
        <span style="font-size:.72rem;background:#f0fdf4;color:#15803d;padding:.2rem .65rem;border-radius:9999px;border:1px solid #bbf7d0;">مشتری مطلع شد ✓</span>
    @endif
</div>

<div class="inc-detail-grid">

    {{-- Left: details + timeline --}}
    <div>

        {{-- Metadata --}}
        <div class="card">
            <div class="card-head">
                <h3>{{ $incident->title }}</h3>
            </div>
            <div class="card-body">
                @if($incident->message)
                <p style="font-size:.85rem;color:#374151;margin-bottom:1.25rem;padding:.75rem;background:#f8fafc;border-radius:.6rem;border:1px solid #e5e7eb;">
                    {{ $incident->message }}
                </p>
                @endif
                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">نوع</span>
                        <span class="meta-value">{{ $incident->typeLabel() }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">سایت</span>
                        <span class="meta-value" style="color:#4f46e5;">{{ $incident->site?->domain ?? 'پلتفرم' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">مسئول</span>
                        <span class="meta-value">{{ $incident->assignedTo?->name ?? 'تعیین نشده' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">تیکت</span>
                        <span class="meta-value">{{ $incident->support_ticket_id ? '#' . $incident->support_ticket_id : '—' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">ایجاد</span>
                        <span class="meta-value">{{ \App\Helpers\NumberHelper::formatFa($incident->created_at->format('Y/m/d H:i')) }}</span>
                    </div>
                    @if($incident->investigating_at)
                    <div class="meta-item">
                        <span class="meta-label">شروع بررسی</span>
                        <span class="meta-value">{{ \App\Helpers\NumberHelper::formatFa($incident->investigating_at->format('Y/m/d H:i')) }}</span>
                    </div>
                    @endif
                    @if($incident->resolved_at)
                    <div class="meta-item">
                        <span class="meta-label">برطرف شد</span>
                        <span class="meta-value">{{ \App\Helpers\NumberHelper::formatFa($incident->resolved_at->format('Y/m/d H:i')) }}</span>
                    </div>
                    @endif
                    @if($incident->closed_at)
                    <div class="meta-item">
                        <span class="meta-label">بسته شد</span>
                        <span class="meta-value">{{ \App\Helpers\NumberHelper::formatFa($incident->closed_at->format('Y/m/d H:i')) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card">
            <div class="card-head">
                <h3>خط زمانی</h3>
            </div>
            <div class="card-body">
                @if($incident->notes->isEmpty())
                    <p style="color:#9ca3af;font-size:.82rem;text-align:center;padding:1rem 0;">هیچ یادداشتی ثبت نشده است.</p>
                @else
                <ul class="timeline">
                    @foreach($incident->notes as $note)
                    <li class="tl-item">
                        <div class="tl-dot {{ $note->is_system ? 'tl-dot--system' : 'tl-dot--user' }}">
                            @if($note->is_system)
                                <svg width="8" height="8" viewBox="0 0 24 24" fill="#94a3b8"><circle cx="12" cy="12" r="10"/></svg>
                            @else
                                <svg width="8" height="8" viewBox="0 0 24 24" fill="#6366f1"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            @endif
                        </div>
                        <div class="tl-content">
                            <div class="tl-note">{{ $note->note }}</div>
                            <div class="tl-meta">
                                {{ $note->is_system ? 'سیستم' : ($note->user?->name ?? 'کاربر') }}
                                · {{ \App\Helpers\NumberHelper::formatFa($note->created_at->format('Y/m/d H:i')) }}
                                @if(! $note->is_system)
                                <form method="POST" action="{{ route('admin.incidents.notes.delete', [$incident->id, $note->id]) }}" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:.7rem;padding:0 .25rem;">حذف</button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @endif

                {{-- Add note --}}
                <form method="POST" action="{{ route('admin.incidents.notes.store', $incident->id) }}" class="note-form" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                    @csrf
                    <textarea name="note" placeholder="یادداشت جدید اضافه کنید..."></textarea>
                    <button type="submit">افزودن یادداشت</button>
                </form>
            </div>
        </div>

    </div>

    {{-- Right: actions --}}
    <div>
        <div class="card">
            <div class="card-head"><h3>عملیات</h3></div>
            <div class="card-body">

                @if($incident->isOpen())
                <form method="POST" action="{{ route('admin.incidents.investigate', $incident->id) }}">
                    @csrf
                    <textarea name="note" placeholder="توضیح (اختیاری)..." style="width:100%;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;font-family:inherit;font-size:.8rem;resize:none;height:60px;margin-bottom:.5rem;"></textarea>
                    <button type="submit" class="action-btn btn-investigate">
                        🔍 شروع بررسی
                    </button>
                </form>
                @endif

                @if($incident->isActive())
                <form method="POST" action="{{ route('admin.incidents.resolve', $incident->id) }}">
                    @csrf
                    <textarea name="note" placeholder="توضیح رفع مشکل (اختیاری)..." style="width:100%;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;font-family:inherit;font-size:.8rem;resize:none;height:60px;margin-bottom:.5rem;"></textarea>
                    <button type="submit" class="action-btn btn-resolve">
                        ✅ برطرف شد — مشتری مطلع شود
                    </button>
                </form>
                @endif

                @if($incident->isResolved())
                <form method="POST" action="{{ route('admin.incidents.close', $incident->id) }}">
                    @csrf
                    <textarea name="note" placeholder="یادداشت بستن (اختیاری)..." style="width:100%;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;font-family:inherit;font-size:.8rem;resize:none;height:60px;margin-bottom:.5rem;"></textarea>
                    <button type="submit" class="action-btn btn-close-inc">
                        🔒 بستن حادثه
                    </button>
                </form>
                @endif

            </div>
        </div>

        {{-- Site summary --}}
        @if($incident->site)
        <div class="card">
            <div class="card-head"><h3>اطلاعات سایت</h3></div>
            <div class="card-body" style="font-size:.82rem;">
                <div style="margin-bottom:.5rem;"><span style="color:#6b7280;">دامنه:</span> <span style="color:#4f46e5;font-weight:600;">{{ $incident->site->domain }}</span></div>
                <div style="margin-bottom:.5rem;"><span style="color:#6b7280;">نام:</span> {{ $incident->site->title }}</div>
                @if($incident->site->domain_expires_at)
                <div style="margin-bottom:.5rem;"><span style="color:#6b7280;">انقضای دامنه:</span> {{ \App\Helpers\NumberHelper::formatFa($incident->site->domain_expires_at->format('Y/m/d')) }}</div>
                @endif
                <a href="{{ route('admin.infrastructure.monitoring.site', $incident->site_id) }}" style="color:#6366f1;font-size:.78rem;text-decoration:none;">مشاهده وضعیت سایت ↗</a>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
