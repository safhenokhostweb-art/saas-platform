@extends('installer.layout')
@section('title', 'پیکربندی زیرساخت')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">مرحله ۵ — زیرساخت</div>
    <div class="card-subtitle">دستورالعمل‌های نصب Supervisor، Cron و Reverb. این موارد را روی سرور اجرا کنید.</div>

    <div class="card-section">
        <div class="section-title">Cron Job — زمان‌بند</div>
        <p style="color:var(--c-muted); font-size:13px; margin-bottom:12px;">
            دستور زیر را با <code>crontab -e</code> به cron سرور اضافه کنید:
        </p>
        <div class="code-block">{{ $cronLine }}</div>
    </div>

    <div class="card-section">
        <div class="section-title">Supervisor — Queue Worker</div>
        <p style="color:var(--c-muted); font-size:13px; margin-bottom:12px;">
            فایل <code>{{ $supervisorConfig['file_path'] }}</code> را با محتوای زیر ایجاد کنید:
        </p>
        <div class="code-block">{{ $supervisorConfig['config'] }}</div>
        <div style="margin-top:12px;">
            <div class="code-block">supervisorctl reread
supervisorctl update
supervisorctl start saas-platform-worker:*</div>
        </div>
    </div>

    <div class="card-section">
        <div class="section-title">Reverb — WebSocket Server</div>
        <p style="color:var(--c-muted); font-size:13px; margin-bottom:12px;">
            فایل <code>{{ $reverbConfig['file_path'] }}</code> را با محتوای زیر ایجاد کنید:
        </p>
        <div class="code-block">{{ $reverbConfig['config'] }}</div>
    </div>

    <div class="alert alert-info">
        ℹ می‌توانید این صفحه را بعداً از پنل مدیریت → زیرساخت مشاهده کنید.
        الان می‌توانید به مرحله بعد بروید و بعداً این موارد را تنظیم کنید.
    </div>

    <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'infrastructure']) }}" method="POST">
        @csrf
        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'fresh','step'=>'platform']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">تأیید و ادامه &larr;</button>
        </div>
    </form>
</div>
@endsection
