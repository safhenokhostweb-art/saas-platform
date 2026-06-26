@extends('installer.layout')
@section('title', 'Installer قفل شده')

@section('content')
<div class="card" style="text-align:center; max-width:560px; margin:0 auto;">
    <div style="font-size:64px; margin-bottom:20px;">🔒</div>
    <div class="card-title">Installer قفل است</div>
    <div class="card-subtitle" style="margin-bottom:28px;">
        پلتفرم قبلاً نصب شده است. فایل <code>storage/install.lock</code> از اجرای مجدد installer جلوگیری می‌کند.
    </div>

    @if(!empty($lockData))
    <div style="background:var(--c-surface2); border-radius:var(--c-radius-sm); padding:20px; text-align:right; margin-bottom:28px;">
        <div class="check-row">
            <span class="check-icon">📅</span>
            <span class="check-label">تاریخ نصب</span>
            <span class="check-message">{{ $lockData['locked_at'] ?? 'نامشخص' }}</span>
        </div>
        <div class="check-row">
            <span class="check-icon">🚀</span>
            <span class="check-label">نوع نصب</span>
            <span class="check-message">{{ ['fresh'=>'نصب تازه','restore'=>'بازیابی','upgrade'=>'ارتقاء'][$lockData['mode'] ?? ''] ?? ($lockData['mode'] ?? 'نامشخص') }}</span>
        </div>
        @if(!empty($lockData['version']))
        <div class="check-row">
            <span class="check-icon">📦</span>
            <span class="check-label">نسخه</span>
            <span class="check-message">{{ $lockData['version'] }}</span>
        </div>
        @endif
    </div>
    @endif

    <div style="border-top: 1px solid var(--c-border); padding-top: 24px; margin-top:8px;">
        <p style="color:var(--c-muted); font-size:13px; margin-bottom:16px;">
            فقط Super Admin می‌تواند installer را باز کند.
        </p>

        @auth
            @if(auth()->user()->is_admin)
            <form action="{{ route('installer.unlock') }}" method="POST">
                @csrf
                <div class="form-group" style="text-align:right;">
                    <label class="form-label">برای تأیید، عبارت <strong>UNLOCK-INSTALLER</strong> را وارد کنید:</label>
                    <input type="text" name="confirm" class="form-control"
                           placeholder="UNLOCK-INSTALLER" dir="ltr" style="text-align:left;"
                           id="unlockInput" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-danger" id="unlockBtn" disabled>
                    🔓 باز کردن Installer
                </button>
            </form>
            <script>
            document.getElementById('unlockInput').addEventListener('input', function() {
                document.getElementById('unlockBtn').disabled = this.value !== 'UNLOCK-INSTALLER';
            });
            </script>
            @else
                <div class="alert alert-warning">برای آنلاک با Super Admin تماس بگیرید.</div>
            @endif
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">ورود به عنوان Admin</a>
        @endauth
    </div>
</div>
@endsection
