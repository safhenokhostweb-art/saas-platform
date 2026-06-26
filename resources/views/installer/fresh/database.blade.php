@extends('installer.layout')
@section('title', 'پیکربندی پایگاه داده')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">مرحله ۲ — پایگاه داده</div>
    <div class="card-subtitle">اطلاعات اتصال MySQL را وارد کنید. پایگاه داده باید از قبل ایجاد شده باشد.</div>

    <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'database']) }}" method="POST">
        @csrf

        <div class="card-section">
            <div class="section-title">اطلاعات اتصال</div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">هاست MySQL *</label>
                    <input type="text" name="db_host" class="form-control"
                           value="{{ old('db_host', '127.0.0.1') }}" required
                           placeholder="127.0.0.1">
                </div>
                <div class="form-group">
                    <label class="form-label">پورت</label>
                    <input type="number" name="db_port" class="form-control"
                           value="{{ old('db_port', '3306') }}" min="1" max="65535">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">نام پایگاه داده *</label>
                <input type="text" name="db_name" class="form-control"
                       value="{{ old('db_name') }}" required
                       placeholder="saas_platform">
                <div class="form-hint">فقط حروف، اعداد، خط تیره و زیرخط مجاز است</div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">نام کاربری *</label>
                    <input type="text" name="db_user" class="form-control"
                           value="{{ old('db_user') }}" required
                           placeholder="root">
                </div>
                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <input type="password" name="db_password" class="form-control"
                           placeholder="خالی برای بدون رمز">
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            ℹ این اطلاعات در فایل <code>.env</code> ذخیره می‌شود. مطمئن شوید پایگاه داده از قبل ایجاد شده است.
        </div>

        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'fresh','step'=>'env_check']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">ذخیره و ادامه &larr;</button>
        </div>
    </form>
</div>
@endsection
