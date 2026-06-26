@extends('installer.layout')
@section('title', 'بررسی محیط')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">مرحله ۱ — بررسی محیط</div>
    <div class="card-subtitle">سرور شما بررسی می‌شود. همه موارد ضروری باید PASS باشند.</div>

    @if($hasCritical)
        <div class="alert alert-danger">
            ⚠ خطاهای بحرانی شناسایی شدند. قبل از ادامه آنها را برطرف کنید.
        </div>
    @else
        <div class="alert alert-success">
            ✓ محیط آماده است. می‌توانید به مرحله بعد بروید.
        </div>
    @endif

    <div class="card-section">
        <div class="section-title">نتایج بررسی</div>
        @foreach($checks as $key => $check)
            <div class="check-row">
                <span class="check-icon">
                    @if($check['status'] === 'pass') ✅
                    @elseif($check['status'] === 'warn') ⚠️
                    @else ❌
                    @endif
                </span>
                <span class="check-label">{{ $check['label'] }}</span>
                <span class="check-message">{{ $check['message'] }}</span>
                <span class="status-badge status-{{ $check['status'] }}">{{ $check['status'] === 'pass' ? 'موفق' : ($check['status'] === 'warn' ? 'هشدار' : 'خطا') }}</span>
            </div>
        @endforeach
    </div>

    <div style="display:flex; gap:16px; margin-bottom:20px;">
        <div style="background:var(--c-surface2); border-radius:var(--c-radius-sm); padding:16px 24px; flex:1; text-align:center;">
            <div style="font-size:28px; font-weight:800; color:var(--c-success);">{{ $summary['pass'] }}</div>
            <div style="font-size:12px; color:var(--c-muted);">موفق</div>
        </div>
        <div style="background:var(--c-surface2); border-radius:var(--c-radius-sm); padding:16px 24px; flex:1; text-align:center;">
            <div style="font-size:28px; font-weight:800; color:var(--c-warning);">{{ $summary['warn'] }}</div>
            <div style="font-size:12px; color:var(--c-muted);">هشدار</div>
        </div>
        <div style="background:var(--c-surface2); border-radius:var(--c-radius-sm); padding:16px 24px; flex:1; text-align:center;">
            <div style="font-size:28px; font-weight:800; color:var(--c-danger);">{{ $summary['fail'] }}</div>
            <div style="font-size:12px; color:var(--c-muted);">خطا</div>
        </div>
    </div>

    <div class="actions-bar">
        <a href="{{ route('installer.index') }}" class="btn btn-secondary">← بازگشت</a>
        <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'env_check']) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" {{ $hasCritical ? 'disabled' : '' }}>
                ادامه — پایگاه داده &larr;
            </button>
        </form>
    </div>
</div>
@endsection
