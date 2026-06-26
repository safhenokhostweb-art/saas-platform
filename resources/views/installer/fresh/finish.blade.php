@extends('installer.layout')
@section('title', 'اتمام نصب')

@section('content')
@include('installer.partials.progress')

<div class="card" style="text-align:center;">
    <div style="font-size:64px; margin-bottom:20px;">🎉</div>
    <div class="card-title">نصب تکمیل می‌شود</div>
    <div class="card-subtitle">پس از کلیک روی «اتمام نصب»، installer قفل می‌شود و نمی‌توان دوباره اجرا کرد.</div>

    <div class="alert alert-warning" style="text-align:right; margin: 24px 0;">
        ⚠ <strong>مهم:</strong> فایل <code>storage/install.lock</code> ایجاد خواهد شد.
        برای باز کردن دوباره installer باید وارد پنل مدیریت شوید و آن را به‌صورت دستی آنلاک کنید.
    </div>

    <div style="background:var(--c-surface2); border-radius:var(--c-radius); padding:24px; text-align:right; margin-bottom:32px;">
        <div class="section-title" style="margin-bottom:16px;">خلاصه نصب</div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">بررسی محیط</span>
            <span class="check-message">تکمیل شد</span>
        </div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">پایگاه داده</span>
            <span class="check-message">پیکربندی شد</span>
        </div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">فایل .env</span>
            <span class="check-message">نوشته شد</span>
        </div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">Migration</span>
            <span class="check-message">اجرا شد</span>
        </div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">Super Admin</span>
            <span class="check-message">ایجاد شد</span>
        </div>
        <div class="check-row">
            <span class="check-icon">✅</span>
            <span class="check-label">اعتبارسنجی</span>
            <span class="check-message">موفق</span>
        </div>
    </div>

    <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'finish']) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-success btn-lg">
            🔒 اتمام نصب و قفل Installer
        </button>
    </form>
</div>
@endsection
