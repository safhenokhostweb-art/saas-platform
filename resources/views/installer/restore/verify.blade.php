@extends('installer.layout')
@section('title', 'تأیید پشتیبان')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">بازیابی — تأیید پشتیبان</div>
    <div class="card-subtitle">جزئیات و یکپارچگی فایل پشتیبان بررسی شد.</div>

    @if(!empty($sourceData))
    <div class="card-section">
        <div class="section-title">فایل انتخاب‌شده</div>
        <div class="check-row">
            <span class="check-icon">📂</span>
            <span class="check-label">منبع</span>
            <span class="check-message">{{ ['local' => 'دیسک محلی', 'arvan' => 'Arvan', 'release' => 'Release Package'][$sourceData['source']] ?? $sourceData['source'] }}</span>
        </div>
        <div class="check-row">
            <span class="check-icon">📄</span>
            <span class="check-label">مسیر فایل</span>
            <span class="check-message" style="font-size:12px; direction:ltr; text-align:left;">{{ basename($sourceData['path']) }}</span>
        </div>
    </div>
    @endif

    @if(!empty($verification))
    <div class="card-section">
        <div class="section-title">نتیجه بررسی</div>
        <div class="alert {{ $verification['valid'] ? 'alert-success' : 'alert-danger' }}">
            {{ $verification['valid'] ? '✅' : '❌' }} {{ $verification['message'] }}
        </div>
        @if(isset($verification['size']))
        <div class="check-row">
            <span class="check-icon">📏</span>
            <span class="check-label">اندازه فایل</span>
            <span class="check-message">{{ number_format($verification['size'] / 1048576, 2) }} MB</span>
        </div>
        @endif
        @if(isset($verification['checksum']))
        <div class="check-row">
            <span class="check-icon">🔐</span>
            <span class="check-label">Checksum (MD5)</span>
            <span class="check-message" style="font-size:11px; direction:ltr; font-family:monospace;">{{ $verification['checksum'] }}</span>
        </div>
        @endif
    </div>
    @endif

    <div class="alert alert-warning">
        ⚠ بازیابی داده‌های فعلی را بازنویسی می‌کند. یک نقطه بازگشت به‌صورت خودکار ایجاد خواهد شد.
    </div>

    <form action="{{ route('installer.process', ['mode'=>'restore','step'=>'verify']) }}" method="POST">
        @csrf
        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'restore','step'=>'source_select']) }}" class="btn btn-secondary">← تغییر منبع</a>
            <button type="submit" class="btn btn-primary" {{ (!empty($verification) && !$verification['valid']) ? 'disabled' : '' }}>
                تأیید و ادامه &larr;
            </button>
        </div>
    </form>
</div>
@endsection
