@extends('installer.layout')
@section('title', 'اجرای ارتقاء')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">ارتقاء — اجرای ارتقاء</div>
    <div class="card-subtitle">پلتفرم ارتقاء خواهد یافت. در صورت خطا، rollback خودکار اجرا می‌شود.</div>

    @if(!empty($snapshotData))
    <div class="card-section">
        <div class="section-title">اسنپ‌شات آماده</div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">زمان اسنپ‌شات</span>
            <span class="check-message">{{ $snapshotData['timestamp'] ?? 'نامشخص' }}</span>
        </div>
        <div class="check-row">
            <span class="check-icon">💾</span>
            <span class="check-label">مسیر</span>
            <span class="check-message" style="font-size:12px; direction:ltr; font-family:monospace;">
                {{ basename($snapshotData['snapshot_path'] ?? '') }}
            </span>
        </div>
        @foreach($snapshotData['results'] ?? [] as $key => $result)
            <div class="check-row">
                <span class="check-icon">{{ $result['success'] ? '✅' : '❌' }}</span>
                <span class="check-label">{{ ['database'=>'پایگاه داده', 'env'=>'فایل .env', 'storage'=>'Storage'][$key] ?? $key }}</span>
                <span class="check-message">{{ $result['message'] }}</span>
            </div>
        @endforeach
    </div>
    @endif

    <div class="card-section">
        <div class="section-title">مراحل ارتقاء</div>
        <div class="check-row">
            <span class="check-icon">🔧</span>
            <span class="check-label">حالت نگهداری</span>
            <span class="check-message">php artisan down</span>
        </div>
        <div class="check-row">
            <span class="check-icon">🗄️</span>
            <span class="check-label">Migration</span>
            <span class="check-message">php artisan migrate --force</span>
        </div>
        <div class="check-row">
            <span class="check-icon">⚡</span>
            <span class="check-label">بهینه‌سازی</span>
            <span class="check-message">config:cache / route:cache / view:cache</span>
        </div>
        <div class="check-row">
            <span class="check-icon">🏥</span>
            <span class="check-label">بررسی سلامت</span>
            <span class="check-message">DB + Redis quick check</span>
        </div>
        <div class="check-row">
            <span class="check-icon">🟢</span>
            <span class="check-label">خاموش کردن maintenance</span>
            <span class="check-message">php artisan up</span>
        </div>
    </div>

    <div class="alert alert-warning">
        ⚠ عبارت تأیید را وارد کنید. در صورت خطا در migration، <strong>rollback خودکار</strong> اجرا خواهد شد.
    </div>

    <form action="{{ route('installer.process', ['mode'=>'upgrade','step'=>'upgrade']) }}" method="POST">
        @csrf
        <div class="form-group">
            <label class="form-label">عبارت <strong>UPGRADE-PLATFORM</strong> را تایپ کنید:</label>
            <input type="text" name="confirm" class="form-control"
                   placeholder="UPGRADE-PLATFORM" dir="ltr" style="text-align:left; letter-spacing:2px; font-weight:600;"
                   id="confirmInput" autocomplete="off">
        </div>

        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'upgrade','step'=>'snapshot']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary" id="upgradeBtn" disabled>
                ⬆️ شروع ارتقاء
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('confirmInput').addEventListener('input', function() {
    document.getElementById('upgradeBtn').disabled = this.value !== 'UPGRADE-PLATFORM';
});
</script>
@endsection
