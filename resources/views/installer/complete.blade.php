@extends('installer.layout')
@section('title', 'نصب تکمیل شد')

@section('content')
<div class="card" style="text-align:center; max-width:600px; margin:0 auto;">
    <div style="font-size:80px; margin-bottom:24px; animation: pulse 2s infinite;">🎊</div>

    <div class="card-title" style="font-size:28px;">پلتفرم آماده است!</div>
    <div class="card-subtitle" style="font-size:16px; margin-bottom:32px;">
        نصب پلتفرم SaaS با موفقیت تکمیل شد. Installer قفل شده است.
    </div>

    @if(!empty($lockData))
    <div style="background:var(--c-surface2); border-radius:var(--c-radius); padding:20px; text-align:right; margin-bottom:32px;">
        <div class="section-title" style="margin-bottom:12px;">گزارش نصب</div>
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
        <div class="check-row">
            <span class="check-icon">🔒</span>
            <span class="check-label">وضعیت Installer</span>
            <span class="check-message">قفل شده</span>
            <span class="status-badge status-pass">ایمن</span>
        </div>
    </div>
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
