@extends('installer.layout')
@section('title', 'نصب پلتفرم')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">مرحله ۴ — نصب پلتفرم</div>
    <div class="card-subtitle">اجرای Migration، ایجاد Super Admin و تنظیمات اولیه پلتفرم.</div>

    <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'platform']) }}" method="POST">
        @csrf

        <div class="card-section">
            <div class="section-title">ایجاد Super Admin</div>
            <div class="form-group">
                <label class="form-label">نام کامل *</label>
                <input type="text" name="admin_name" class="form-control"
                       value="{{ old('admin_name') }}" required placeholder="مدیر پلتفرم">
            </div>
            <div class="form-group">
                <label class="form-label">شماره موبایل *</label>
                <input type="text" name="admin_mobile" class="form-control"
                       value="{{ old('admin_mobile') }}" required
                       placeholder="09123456789" dir="ltr" style="text-align:left;">
                <div class="form-hint">فرمت: 09XXXXXXXXX</div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">رمز عبور *</label>
                    <input type="password" name="admin_password" class="form-control"
                           required minlength="8">
                    <div class="form-hint">حداقل ۸ کاراکتر</div>
                </div>
                <div class="form-group">
                    <label class="form-label">تأیید رمز عبور *</label>
                    <input type="password" name="admin_password_confirmation" class="form-control" required>
                </div>
            </div>
        </div>

        <div class="card-section">
            <div class="section-title">عملیاتی که اجرا می‌شوند</div>
            <div class="check-row">
                <span class="check-icon">🗄️</span>
                <span class="check-label">php artisan migrate</span>
                <span class="check-message">ایجاد تمام جداول پایگاه داده</span>
            </div>
            <div class="check-row">
                <span class="check-icon">👤</span>
                <span class="check-label">Super Admin</span>
                <span class="check-message">ایجاد حساب مدیر ارشد</span>
            </div>
            <div class="check-row">
                <span class="check-icon">⚙️</span>
                <span class="check-label">Platform Settings</span>
                <span class="check-message">ثبت تنظیمات اولیه پلتفرم</span>
            </div>
        </div>

        <div class="alert alert-warning">
            ⚠ این مرحله Migration اجرا می‌کند. اطمینان حاصل کنید پایگاه داده خالی یا آماده است.
        </div>

        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'fresh','step'=>'environment']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">اجرا و ادامه &larr;</button>
        </div>
    </form>
</div>
@endsection
