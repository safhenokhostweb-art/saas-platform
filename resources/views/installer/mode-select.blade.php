@extends('installer.layout')
@section('title', 'انتخاب نوع نصب')

@section('content')
<div style="text-align:center; margin-bottom: 40px;">
    <h1 style="font-size:28px; font-weight:800; margin-bottom:10px;">نصب‌کننده پلتفرم SaaS</h1>
    <p style="color:var(--c-muted); font-size:15px;">نوع عملیات مورد نظر خود را انتخاب کنید</p>
</div>

<form action="{{ route('installer.select-mode') }}" method="POST" id="modeForm">
    @csrf
    <input type="hidden" name="mode" id="selectedMode" value="">

    <div class="mode-grid">

        {{-- MODE 1: Fresh Install --}}
        <div class="mode-card" data-mode="fresh">
            <span class="mode-icon">🚀</span>
            <div class="mode-title">نصب تازه</div>
            <div class="mode-desc">
                نصب کامل پلتفرم روی سرور جدید.<br>
                شامل بررسی محیط، پیکربندی پایگاه داده، ایجاد Super Admin و راه‌اندازی زیرساخت.
            </div>
            <div style="margin-top:16px;">
                <span class="status-badge status-pass">۷ مرحله</span>
            </div>
        </div>

        {{-- MODE 2: Restore --}}
        <div class="mode-card" data-mode="restore">
            <span class="mode-icon">♻️</span>
            <div class="mode-title">بازیابی پلتفرم</div>
            <div class="mode-desc">
                بازیابی از پشتیبان موجود.<br>
                منابع: دیسک محلی، Arvan Object Storage، یا پکیج release.
            </div>
            <div style="margin-top:16px;">
                <span class="status-badge status-warn">۴ مرحله</span>
            </div>
        </div>

        {{-- MODE 3: Upgrade --}}
        <div class="mode-card" data-mode="upgrade">
            <span class="mode-icon">⬆️</span>
            <div class="mode-title">ارتقاء پلتفرم</div>
            <div class="mode-desc">
                ارتقاء نسخه جاری پلتفرم.<br>
                اسنپ‌شات خودکار → migration → بهینه‌سازی → rollback در صورت خطا.
            </div>
            <div style="margin-top:16px;">
                <span class="status-badge status-pass">۴ مرحله</span>
            </div>
        </div>

    </div>

    <div style="text-align:center; margin-top:40px; display:none;" id="startBtnWrapper">
        <button type="submit" class="btn btn-primary btn-lg" id="startBtn">
            شروع &larr;
        </button>
    </div>
</form>

<script>
    var selectedModeInput = document.getElementById('selectedMode');
    var startBtnWrapper   = document.getElementById('startBtnWrapper');

    document.querySelectorAll('.mode-card').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.mode-card').forEach(function(c) {
                c.classList.remove('selected');
            });
            this.classList.add('selected');
            selectedModeInput.value = this.getAttribute('data-mode');
            startBtnWrapper.style.display = 'block';
        });
    });
</script>
@endsection
