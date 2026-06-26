@extends('installer.layout')
@section('title', 'اعتبارسنجی ارتقاء')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">ارتقاء — اعتبارسنجی</div>
    <div class="card-subtitle">بررسی سلامت پلتفرم پس از ارتقاء.</div>

    @if($healthResult['pass'])
        <div class="alert alert-success">✅ ارتقاء موفق — پلتفرم سالم است (DR Score: {{ $healthResult['dr_score'] }}/100)</div>
    @else
        <div class="alert alert-warning">⚠ برخی بررسی‌ها هشدار دارند — DR Score: {{ $healthResult['dr_score'] }}/100</div>
    @endif

    <div class="card-section">
        <div class="section-title">بررسی سریع</div>
        <div class="check-row">
            <span class="check-icon">{{ $healthResult['dr_score'] >= 50 ? '✅' : '⚠️' }}</span>
            <span class="check-label">نمره DR</span>
            <span class="check-message">{{ $healthResult['dr_score'] }}/100 — {{ $healthResult['dr_grade'] }}</span>
            <span class="status-badge status-{{ $healthResult['dr_score'] >= 70 ? 'pass' : ($healthResult['dr_score'] >= 50 ? 'warn' : 'fail') }}">
                {{ $healthResult['dr_grade'] }}
            </span>
        </div>
        @foreach($healthResult['custom'] as $key => $check)
            <div class="check-row">
                <span class="check-icon">{{ $check['status'] === 'pass' ? '✅' : '⚠️' }}</span>
                <span class="check-label">{{ ['backup_dir'=>'Backup Dir', 'mail_config'=>'ایمیل', 'arvan_connect'=>'Arvan'][$key] ?? $key }}</span>
                <span class="check-message">{{ $check['message'] }}</span>
            </div>
        @endforeach
    </div>

    <form action="{{ route('installer.process', ['mode'=>'upgrade','step'=>'validate']) }}" method="POST">
        @csrf
        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'upgrade','step'=>'upgrade']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">اتمام ارتقاء &larr;</button>
        </div>
    </form>
</div>
@endsection
