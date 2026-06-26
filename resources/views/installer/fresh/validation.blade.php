@extends('installer.layout')
@section('title', 'اعتبارسنجی نصب')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">مرحله ۶ — اعتبارسنجی</div>
    <div class="card-subtitle">بررسی نهایی سلامت پلتفرم پس از نصب.</div>

    @if($healthResult['pass'])
        <div class="alert alert-success">✅ پلتفرم سالم است و آماده استفاده — نمره DR: {{ $healthResult['dr_score'] }}/100</div>
    @else
        <div class="alert alert-warning">⚠ برخی بررسی‌ها هشدار دارند — نمره DR: {{ $healthResult['dr_score'] }}/100</div>
    @endif

    <div class="card-section">
        <div class="section-title">نمره آمادگی بازیابی (DR Score)</div>
        <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
            <div style="width:80px; height:80px; border-radius:50%; background: conic-gradient(var(--c-{{ $healthResult['dr_score'] >= 70 ? 'success' : ($healthResult['dr_score'] >= 50 ? 'warning' : 'danger') }}) {{ $healthResult['dr_score'] }}%, var(--c-border) 0); display:flex; align-items:center; justify-content:center;">
                <div style="width:60px; height:60px; border-radius:50%; background:var(--c-surface); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px;">
                    {{ $healthResult['dr_score'] }}
                </div>
            </div>
            <div>
                <div style="font-size:22px; font-weight:700;">{{ $healthResult['dr_grade'] }}</div>
                <div style="color:var(--c-muted); font-size:13px;">از ۱۰۰ امتیاز</div>
            </div>
        </div>
    </div>

    <div class="card-section">
        <div class="section-title">بررسی‌های اضافی نصب</div>
        @foreach($healthResult['custom'] as $key => $check)
            <div class="check-row">
                <span class="check-icon">
                    {{ $check['status'] === 'pass' ? '✅' : '⚠️' }}
                </span>
                <span class="check-label">{{ ['backup_dir' => 'پوشه Backup', 'mail_config' => 'تنظیمات ایمیل', 'arvan_connect' => 'اتصال Arvan'][$key] ?? $key }}</span>
                <span class="check-message">{{ $check['message'] }}</span>
                <span class="status-badge status-{{ $check['status'] }}">{{ $check['status'] === 'pass' ? 'موفق' : 'هشدار' }}</span>
            </div>
        @endforeach
    </div>

    <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'validation']) }}" method="POST">
        @csrf
        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'fresh','step'=>'infrastructure']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">تأیید و اتمام نصب &larr;</button>
        </div>
    </form>
</div>
@endsection
