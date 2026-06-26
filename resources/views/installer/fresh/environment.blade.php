@extends('installer.layout')
@section('title', 'تنظیمات محیط')

@section('content')
@include('installer.partials.progress')

<div class="card">
    <div class="card-title">مرحله ۳ — تنظیمات محیط</div>
    <div class="card-subtitle">پیکربندی اصلی پلتفرم، صف، کش و سرویس‌های جانبی.</div>

    <form action="{{ route('installer.process', ['mode'=>'fresh','step'=>'environment']) }}" method="POST">
        @csrf

        <div class="card-section">
            <div class="section-title">تنظیمات اصلی</div>
            <div class="form-group">
                <label class="form-label">نام پلتفرم *</label>
                <input type="text" name="app_name" class="form-control"
                       value="{{ old('app_name', config('app.name', 'SaaS Platform')) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">آدرس URL پلتفرم *</label>
                <input type="url" name="app_url" class="form-control"
                       value="{{ old('app_url', config('app.url', 'https://')) }}" required
                       placeholder="https://panel.example.com">
                <div class="form-hint">آدرس کامل با https (بدون اسلش انتهایی)</div>
            </div>
            <div class="form-group">
                <label class="form-label">منطقه زمانی *</label>
                <select name="app_timezone" class="form-control">
                    <option value="Asia/Tehran" {{ config('app.timezone') === 'Asia/Tehran' ? 'selected' : '' }}>Asia/Tehran (ایران)</option>
                    <option value="UTC">UTC</option>
                    <option value="Asia/Dubai">Asia/Dubai</option>
                </select>
            </div>
        </div>

        <div class="card-section">
            <div class="section-title">صف و کش</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">درایور صف</label>
                    <select name="queue_driver" class="form-control">
                        <option value="redis" selected>Redis (توصیه‌شده)</option>
                        <option value="database">Database</option>
                        <option value="sync">Sync (توسعه)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">درایور کش</label>
                    <select name="cache_driver" class="form-control">
                        <option value="redis" selected>Redis (توصیه‌شده)</option>
                        <option value="file">File</option>
                    </select>
                </div>
            </div>
        </div>

        @if(empty($currentAppKey))
        <div class="alert alert-warning">
            ⚠ APP_KEY تنظیم نشده — به‌صورت خودکار در این مرحله تولید می‌شود.
        </div>
        @else
        <div class="alert alert-success">
            ✓ APP_KEY از قبل تنظیم شده است.
        </div>
        @endif

        <div class="actions-bar">
            <a href="{{ route('installer.step', ['mode'=>'fresh','step'=>'database']) }}" class="btn btn-secondary">← بازگشت</a>
            <button type="submit" class="btn btn-primary">ذخیره و ادامه &larr;</button>
        </div>
    </form>
</div>
@endsection
