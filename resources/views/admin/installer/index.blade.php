@extends('layouts.admin')
@section('header', 'وضعیت نصب پلتفرم')

@push('head')
<style>
.inst-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,0.05);padding:1.5rem;margin-bottom:1.25rem}
.inst-card__title{font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6366f1;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid #e2e8f0}
.inst-row{display:flex;align-items:flex-start;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #f1f5f9;font-size:.875rem}
.inst-row:last-child{border-bottom:none}
.inst-key{color:#64748b;flex-shrink:0;width:200px;font-size:.8rem}
.inst-val{color:#1e293b;font-family:monospace;font-size:.8rem;word-break:break-all;flex:1}
.badge-pass{display:inline-flex;align-items:center;gap:.3rem;background:#dcfce7;color:#166534;padding:.25rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.badge-warn{display:inline-flex;align-items:center;gap:.3rem;background:#fef3c7;color:#92400e;padding:.25rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.badge-fail{display:inline-flex;align-items:center;gap:.3rem;background:#fee2e2;color:#991b1b;padding:.25rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.badge-info{display:inline-flex;align-items:center;gap:.3rem;background:#eff6ff;color:#1e40af;padding:.25rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.badge-missing{display:inline-flex;align-items:center;gap:.3rem;background:#f8fafc;color:#64748b;padding:.25rem .7rem;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.dl-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .9rem;border-radius:.5rem;font-size:.8rem;font-weight:600;text-decoration:none;transition:all .15s;border:1px solid transparent}
.dl-btn--primary{background:#6366f1;color:#fff;border-color:#6366f1}
.dl-btn--primary:hover{background:#4f46e5}
.dl-btn--secondary{background:#f8fafc;color:#334155;border-color:#e2e8f0}
.dl-btn--secondary:hover{background:#f1f5f9;border-color:#94a3b8}
.file-row{display:flex;align-items:center;gap:.5rem;padding:.4rem .75rem;background:#f8fafc;border-radius:.5rem;margin:.25rem 0;font-size:.78rem}
.file-icon{color:#6366f1}
.verify-result{display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem;margin-top:.75rem}
.verify-result.visible{display:block}
.verify-pass-item{color:#166534;font-size:.78rem;margin:.15rem 0}
.verify-fail-item{color:#991b1b;font-size:.78rem;margin:.15rem 0}
.overall-banner{border-radius:1rem;padding:1.25rem 1.5rem;display:flex;align-items:center;gap:1.5rem;margin-bottom:1.25rem}
.overall-banner--pass{background:linear-gradient(135deg,#dcfce7,#bbf7d0);border:1px solid #86efac}
.overall-banner--warn{background:linear-gradient(135deg,#fef9c3,#fef08a);border:1px solid #fcd34d}
.overall-banner--fail{background:linear-gradient(135deg,#fee2e2,#fecaca);border:1px solid #f87171}
.overall-banner--none{background:#f8fafc;border:1px solid #e2e8f0}
</style>
@endpush

@section('content')

{{-- Page Header --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">وضعیت نصب پلتفرم</h1>
        <p class="text-gray-500 text-sm mt-1">اطلاعات نصب، Baseline Snapshot، و وضعیت Manifest — فقط خواندنی</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        @if($hasHtml)
        <a href="{{ route('admin.installer.report', 'html') }}" class="dl-btn dl-btn--secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            گزارش HTML
        </a>
        @endif
        @if($hasPdf)
        <a href="{{ route('admin.installer.report', 'pdf') }}" class="dl-btn dl-btn--primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            گزارش PDF
        </a>
        @endif
    </div>
</div>

{{-- Overall Status Banner --}}
@php
    $os = $lockData['overall_status'] ?? null;
    $cert = $lockData['certification'] ?? null;
    $bannerClass = match($os) {
        'PASS'    => 'pass',
        'WARNING' => 'warn',
        'FAILED'  => 'fail',
        default   => 'none',
    };
@endphp
<div class="overall-banner overall-banner--{{ $bannerClass }}">
    <div>
        <div style="font-size:.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Overall Status</div>
        <div style="font-size:2rem;font-weight:900;color:{{ $os === 'PASS' ? '#166534' : ($os === 'WARNING' ? '#92400e' : ($os === 'FAILED' ? '#991b1b' : '#334155')) }}">
            {{ $os ?? 'نصب انجام نشده' }}
        </div>
    </div>
    @if($cert)
    <div>
        <div style="font-size:.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Certification</div>
        <div style="font-size:.9rem;font-weight:700;font-family:monospace;color:#1e293b">{{ $cert }}</div>
    </div>
    @endif
    @if(!$isLocked)
    <div class="mr-auto">
        <span class="badge-warn">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            Installer قفل نشده
        </span>
    </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- Current Installation --}}
    <div class="inst-card">
        <div class="inst-card__title">نصب جاری</div>
        @if($lockData)
        <div class="inst-row">
            <span class="inst-key">Installation UUID</span>
            <span class="inst-val">{{ $lockData['report_id'] ?? '—' }}</span>
        </div>
        <div class="inst-row">
            <span class="inst-key">نوع نصب</span>
            <span class="inst-val">
                @php $modes = ['fresh'=>'نصب تازه','restore'=>'بازیابی','upgrade'=>'ارتقاء']; @endphp
                {{ $modes[$lockData['mode'] ?? ''] ?? ($lockData['mode'] ?? '—') }}
            </span>
        </div>
        <div class="inst-row">
            <span class="inst-key">تاریخ تکمیل</span>
            <span class="inst-val">{{ $lockData['completed_at'] ?? '—' }}</span>
        </div>
        <div class="inst-row">
            <span class="inst-key">وضعیت Installer</span>
            <span class="inst-val">
                @if($isLocked)
                    <span class="badge-pass">&#x1F512; قفل‌شده</span>
                @else
                    <span class="badge-warn">&#x26A0; باز</span>
                @endif
            </span>
        </div>
        @else
        <p class="text-gray-400 text-sm">هنوز نصبی انجام نشده است.</p>
        @endif
    </div>

    {{-- Current Baseline --}}
    <div class="inst-card">
        <div class="inst-card__title">Baseline Snapshot جاری</div>
        @if($primaryBaseline)
        <div class="inst-row">
            <span class="inst-key">نام Baseline</span>
            <span class="inst-val" style="font-weight:700">{{ $primaryBaseline['name'] }}</span>
        </div>
        <div class="inst-row">
            <span class="inst-key">نوع</span>
            <span class="inst-val">
                @if($primaryBaseline['is_initial'])
                    <span class="badge-info">INITIAL</span>
                @else
                    <span class="badge-info">TIMESTAMPED</span>
                @endif
            </span>
        </div>
        <div class="inst-row">
            <span class="inst-key">تاریخ ایجاد</span>
            <span class="inst-val">{{ $primaryBaseline['created_at'] ?? '—' }}</span>
        </div>
        <div class="inst-row">
            <span class="inst-key">Installation UUID</span>
            <span class="inst-val">{{ $primaryBaseline['installation_uuid'] ?? '—' }}</span>
        </div>
        <div class="inst-row">
            <span class="inst-key">Platform Version</span>
            <span class="inst-val">{{ $primaryBaseline['platform_version'] ?? '—' }}</span>
        </div>
        <div class="inst-row">
            <span class="inst-key">مسیر</span>
            <span class="inst-val" style="font-size:.7rem">{{ $primaryBaseline['path'] }}</span>
        </div>
        @else
        <p class="text-gray-400 text-sm">هیچ Baseline ای ایجاد نشده است.</p>
        @endif
    </div>

    {{-- Manifest Status --}}
    <div class="inst-card">
        <div class="inst-card__title">وضعیت Manifest</div>
        @if($manifestStatus)
        <div class="inst-row">
            <span class="inst-key">وضعیت Manifest</span>
            <span class="inst-val">
                @if($manifestStatus['pass'] ?? false)
                    <span class="badge-pass">&#10003; OK</span>
                @elseif(($manifestStatus['status'] ?? '') === 'MISSING')
                    <span class="badge-missing">MISSING</span>
                @elseif(($manifestStatus['status'] ?? '') === 'INVALID_JSON')
                    <span class="badge-fail">INVALID JSON</span>
                @elseif(($manifestStatus['status'] ?? '') === 'INCOMPLETE')
                    <span class="badge-warn">INCOMPLETE</span>
                @else
                    <span class="badge-fail">{{ $manifestStatus['status'] ?? 'UNKNOWN' }}</span>
                @endif
            </span>
        </div>
        @if(!empty($manifestStatus['manifest_checksum']))
        <div class="inst-row">
            <span class="inst-key">Manifest Checksum</span>
            <span class="inst-val" style="font-size:.7rem;word-break:break-all">{{ $manifestStatus['manifest_checksum'] }}</span>
        </div>
        @endif
        @if(!empty($manifestStatus['created_at_jalali']))
        <div class="inst-row">
            <span class="inst-key">تاریخ Manifest (جلالی)</span>
            <span class="inst-val">{{ $manifestStatus['created_at_jalali'] }}</span>
        </div>
        @endif
        @if(!empty($lockData['manifest_checksum']) && empty($manifestStatus['manifest_checksum']))
        <div class="inst-row">
            <span class="inst-key">Manifest Checksum (از lock)</span>
            <span class="inst-val" style="font-size:.7rem;word-break:break-all">{{ $lockData['manifest_checksum'] }}</span>
        </div>
        @endif
        @else
        <p class="text-gray-400 text-sm">Manifest در دسترس نیست.</p>
        @endif

        {{-- Download Manifest --}}
        @if($primaryBaseline && $primaryBaseline['has_manifest'])
        <div class="mt-4 flex gap-2 flex-wrap">
            <a href="{{ route('admin.installer.manifest', ['path' => $primaryBaseline['path']]) }}"
               class="dl-btn dl-btn--secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                دانلود manifest.json
            </a>
            <a href="{{ route('admin.installer.baseline-meta', ['path' => $primaryBaseline['path']]) }}"
               class="dl-btn dl-btn--secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                دانلود Baseline Meta
            </a>
        </div>
        @endif
    </div>

    {{-- Verification --}}
    <div class="inst-card">
        <div class="inst-card__title">تأیید صحت (Verification)</div>
        <p class="text-gray-500 text-sm mb-4">
            بررسی کامل SHA-256 برای تمام فایل‌های Baseline. عملیات فقط-خواندنی است — هیچ فایلی تغییر نمی‌کند.
        </p>
        @if($primaryBaseline)
        <button onclick="runVerification('{{ addslashes($primaryBaseline['path']) }}')"
                id="verify-btn"
                class="dl-btn dl-btn--secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            اجرای Verification
        </button>
        <div id="verify-result" class="verify-result"></div>
        @else
        <p class="text-gray-400 text-sm">هیچ Baseline ای برای تأیید وجود ندارد.</p>
        @endif
    </div>

</div>

{{-- All Baselines List --}}
@if(count($baselines) > 0)
<div class="inst-card mt-5">
    <div class="inst-card__title">تمام Baseline‌ها ({{ count($baselines) }} عدد)</div>
    @foreach($baselines as $b)
    <div class="inst-row" style="flex-wrap:wrap;gap:.5rem;align-items:center">
        <span class="inst-key" style="width:auto;flex:1;min-width:200px;font-weight:{{ $b['is_initial'] ? '700' : '400' }}">
            {{ $b['name'] }}
            @if($b['is_initial'])<span class="badge-info" style="margin-right:.4rem">INITIAL</span>@endif
        </span>
        <span class="inst-val" style="width:auto">{{ $b['created_at'] ?? '—' }}</span>
        @if($b['has_manifest'])
            <span class="badge-pass">manifest &#10003;</span>
        @else
            <span class="badge-missing">بدون manifest</span>
        @endif
        <div style="display:flex;gap:.4rem">
            <a href="{{ route('admin.installer.manifest', ['path' => $b['path']]) }}"
               class="dl-btn dl-btn--secondary" style="padding:.25rem .6rem;font-size:.7rem">manifest.json</a>
            <a href="{{ route('admin.installer.baseline-meta', ['path' => $b['path']]) }}"
               class="dl-btn dl-btn--secondary" style="padding:.25rem .6rem;font-size:.7rem">baseline_meta.json</a>
            <button onclick="runVerification('{{ addslashes($b['path']) }}', this)"
                    class="dl-btn dl-btn--secondary" style="padding:.25rem .6rem;font-size:.7rem">Verify</button>
        </div>
    </div>
    @endforeach
</div>
@endif

@push('scripts')
<script>
function runVerification(path, btn) {
    const resultDiv = document.getElementById('verify-result');
    const mainBtn   = document.getElementById('verify-btn');

    if (resultDiv) {
        resultDiv.classList.remove('visible');
        resultDiv.innerHTML = '<div class="text-sm text-gray-500">در حال تأیید...</div>';
    }
    if (mainBtn) mainBtn.disabled = true;
    if (btn)     btn.disabled = true;

    fetch('{{ route("admin.installer.verify") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ path: path }),
    })
    .then(r => r.json())
    .then(data => {
        let html = '';
        const badgeClass = data.pass ? 'badge-pass' : 'badge-fail';
        html += `<div class="flex items-center gap-2 mb-3"><span class="${badgeClass}" style="font-size:.85rem">${data.status || (data.pass ? 'PASS' : 'FAILED')}</span></div>`;

        if (data.passed && data.passed.length) {
            html += `<div style="font-weight:600;font-size:.78rem;color:#374151;margin-bottom:.3rem">&#10003; موارد تأیید شده (${data.passed.length})</div>`;
            data.passed.forEach(p => { html += `<div class="verify-pass-item">&#10003; ${p}</div>`; });
        }
        if (data.failures && data.failures.length) {
            html += `<div style="font-weight:600;font-size:.78rem;color:#991b1b;margin-top:.5rem;margin-bottom:.3rem">&#10007; خطاها (${data.failures.length})</div>`;
            data.failures.forEach(f => { html += `<div class="verify-fail-item">&#10007; ${f}</div>`; });
        }
        if (data.error) {
            html += `<div class="verify-fail-item">&#10007; ${data.error}</div>`;
        }

        if (resultDiv) {
            resultDiv.innerHTML = html;
            resultDiv.classList.add('visible');
        }
    })
    .catch(err => {
        if (resultDiv) {
            resultDiv.innerHTML = `<div class="verify-fail-item">خطا: ${err.message}</div>`;
            resultDiv.classList.add('visible');
        }
    })
    .finally(() => {
        if (mainBtn) mainBtn.disabled = false;
        if (btn)     btn.disabled = false;
    });
}
</script>
@endpush

@endsection
