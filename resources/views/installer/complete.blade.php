@extends('installer.layout')
@section('title', 'نصب تکمیل شد')

@section('content')
<div class="card" style="text-align:center; max-width:640px; margin:0 auto;">
    <div style="font-size:80px; margin-bottom:24px; animation: pulse 2s infinite;">🎊</div>

    <div class="card-title" style="font-size:28px;">پلتفرم آماده است!</div>
    <div class="card-subtitle" style="font-size:16px; margin-bottom:32px;">
        نصب پلتفرم SaaS با موفقیت تکمیل شد. Installer قفل شده است.
    </div>

    @if(!empty($lockData))
    <div style="background:var(--c-surface2); border-radius:var(--c-radius); padding:20px; text-align:right; margin-bottom:24px;">
        <div class="section-title" style="margin-bottom:12px;">اطلاعات نصب</div>
        <div class="check-row">
            <span class="check-icon">🚀</span>
            <span class="check-label">نوع</span>
            <span class="check-message">{{ ['fresh'=>'نصب تازه','restore'=>'بازیابی','upgrade'=>'ارتقاء'][$lockData['mode'] ?? ''] ?? 'نصب' }}</span>
        </div>
        <div class="check-row">
            <span class="check-icon">📅</span>
            <span class="check-label">تاریخ تکمیل</span>
            <span class="check-message">{{ $lockData['completed_at'] ?? now()->toISOString() }}</span>
        </div>
        @if(!empty($lockData['report_id']))
        <div class="check-row">
            <span class="check-icon">🆔</span>
            <span class="check-label">Installation ID</span>
            <span class="check-message" style="font-family:monospace; font-size:12px;">{{ $lockData['report_id'] }}</span>
        </div>
        @endif
        @if(!empty($lockData['baseline_path']))
        <div class="check-row">
            <span class="check-icon">💾</span>
            <span class="check-label">Baseline Snapshot</span>
            <span class="check-message" style="font-size:12px;">INITIAL_INSTALLATION_BASELINE</span>
            <span class="status-badge status-pass">ایجاد شد</span>
        </div>
        @endif
        <div class="check-row">
            <span class="check-icon">🔒</span>
            <span class="check-label">وضعیت Installer</span>
            <span class="check-message">قفل شده</span>
            <span class="status-badge status-pass">ایمن</span>
        </div>
    </div>

    {{-- Report Download Section --}}
    @if(!empty($hasHtml) || !empty($hasPdf))
    <div style="background:var(--c-surface); border:1px solid var(--c-border); border-radius:var(--c-radius); padding:20px; margin-bottom:24px; text-align:right;">
        <div class="section-title" style="margin-bottom:16px;">📋 گزارش نصب</div>
        <p style="font-size:13px; color:var(--c-muted); margin-bottom:16px;">
            گزارش کامل نصب به‌صورت دائمی در سرور ذخیره شده است.
            می‌توانید نسخه HTML یا PDF را دانلود کنید.
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            @if(!empty($hasHtml))
            <a href="{{ route('installer.report-download', 'html') }}"
               class="btn btn-secondary"
               style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                دانلود HTML
            </a>
            @endif
            @if(!empty($hasPdf))
            <a href="{{ route('installer.report-download', 'pdf') }}"
               class="btn btn-primary"
               style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                دانلود PDF
            </a>
            @endif
        </div>
        <p style="font-size:11px; color:var(--c-muted); margin-top:12px; direction:ltr; text-align:left;">
            Stored at: <code>storage/app/installation-reports/</code>
        </p>
    </div>
    @endif
    @endif

    <div class="alert alert-info" style="text-align:right; margin-bottom:28px;">
        ℹ <strong>قدم‌های بعدی:</strong>
        <ol style="margin-top:8px; margin-right:16px; line-height:2;">
            <li>Supervisor را راه‌اندازی کنید (worker + restore-worker + reverb)</li>
            <li>Cron را به crontab اضافه کنید</li>
            <li>ArvanCloud CDN را فعال کنید</li>
            <li>از پنل مدیریت وارد شوید و تنظیمات اولیه را کامل کنید</li>
        </ol>
    </div>

    <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">🏠 ورود به پنل مدیریت</a>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
</style>
@endsection
