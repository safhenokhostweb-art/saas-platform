@extends('installer.layout')
@section('title', 'اسنپ‌شات قبل از ارتقاء')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">ارتقاء — اسنپ‌شات خودکار</div>
    <div class="card-subtitle">قبل از ارتقاء، یک اسنپ‌شات کامل ایجاد می‌شود تا امکان rollback وجود داشته باشد.</div>

    <div class="card-section">
        <div class="section-title">آنچه اسنپ‌شات می‌شود</div>
        <div class="check-row">
            <span class="check-icon">🗄️</span>
            <span class="check-label">پایگاه داده</span>
            <span class="check-message">mysqldump + gzip در storage/upgrade_snapshots/</span>
        </div>
        <div class="check-row">
            <span class="check-icon">⚙️</span>
            <span class="check-label">فایل .env</span>
            <span class="check-message">نسخه پشتیبان در پوشه اسنپ‌شات</span>
        </div>
        <div class="check-row">
            <span class="check-icon">☁️</span>
            <span class="check-label">Storage Files</span>
            <span class="check-message">از Arvan Object Storage قابل بازیابی است</span>
        </div>
    </div>

    <div class="alert alert-info">
        ℹ اسنپ‌شات می‌تواند چند دقیقه طول بکشد (بسته به اندازه پایگاه داده).
        صفحه را نبندید.
    </div>

    <form action="{{ route('installer.process', ['mode'=>'upgrade','step'=>'snapshot']) }}" method="POST">
        @csrf
        <div class="actions-bar">
            <a href="{{ route('installer.index') }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">📸 ایجاد اسنپ‌شات و ادامه &larr;</button>
        </div>
    </form>
</div>
@endsection
