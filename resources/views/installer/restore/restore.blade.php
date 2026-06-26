@extends('installer.layout')
@section('title', 'اجرای بازیابی')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">بازیابی — اجرای بازیابی</div>
    <div class="card-subtitle">برای شروع بازیابی، عبارت تأیید را وارد کنید.</div>

    <div class="alert alert-danger">
        🚨 <strong>هشدار بحرانی:</strong>
        این عملیات داده‌های فعلی پلتفرم را بازنویسی می‌کند.
        قبل از شروع، یک نقطه بازگشت خودکار ایجاد می‌شود.
        <strong>این عملیات قابل undo نیست مگر از طریق rollback دستی.</strong>
    </div>

    @if(!empty($sourceData))
    <div class="card-section">
        <div class="section-title">خلاصه عملیات</div>
        <div class="check-row">
            <span class="check-icon">📁</span>
            <span class="check-label">منبع</span>
            <span class="check-message">{{ ['local'=>'دیسک محلی','arvan'=>'Arvan','release'=>'Release'][$sourceData['source']] ?? $sourceData['source'] }}</span>
        </div>
        <div class="check-row">
            <span class="check-icon">📄</span>
            <span class="check-label">فایل</span>
            <span class="check-message" style="font-size:12px; direction:ltr;">{{ basename($sourceData['path']) }}</span>
        </div>
        <div class="check-row">
            <span class="check-icon">💾</span>
            <span class="check-label">نقطه بازگشت</span>
            <span class="check-message">به‌صورت خودکار قبل از بازیابی ایجاد می‌شود</span>
        </div>
    </div>
    @endif

    <form action="{{ route('installer.process', ['mode'=>'restore','step'=>'restore']) }}" method="POST" id="restoreForm">
        @csrf

        <div class="form-group">
            <label class="form-label">تأییدیه — عبارت <strong>RESTORE-PLATFORM</strong> را تایپ کنید:</label>
            <input type="text" name="confirm" class="form-control"
                   placeholder="RESTORE-PLATFORM" dir="ltr" style="text-align:left; letter-spacing:2px; font-weight:600;"
                   id="confirmInput" autocomplete="off">
        </div>

        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'restore','step'=>'verify']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-danger" id="restoreBtn" disabled>
                🔄 شروع بازیابی
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('confirmInput').addEventListener('input', function() {
    document.getElementById('restoreBtn').disabled = this.value !== 'RESTORE-PLATFORM';
});
</script>
@endsection
