@extends('installer.layout')
@section('title', 'نتیجه بازیابی')

@section('content')
@include('installer.partials.progress')

@php
    $success = $restoreStepData['success'] ?? null;
    $queued  = $restoreStepData['queued']  ?? false;
    $message = $restoreStepData['message'] ?? '';
@endphp

<div class="card" style="text-align:center;">

    @if($queued)
        {{-- Arvan source — queued async --}}
        <div style="font-size:64px; margin-bottom:20px;">⏳</div>
        <div class="card-title">بازیابی در صف است</div>
        <div class="card-subtitle" style="margin-bottom:32px;">
            عملیات بازیابی از Arvan به صف ارسال شد. می‌توانید پیشرفت را از مرکز DR دنبال کنید.
        </div>
        <div class="alert alert-info" style="text-align:right;">
            ℹ برای مشاهده لاگ‌های بازیابی: پنل مدیریت ← مرکز DR ← لاگ‌های بازیابی
        </div>
    @elseif($success === true)
        {{-- Local source — sync success --}}
        <div style="font-size:64px; margin-bottom:20px;">✅</div>
        <div class="card-title">بازیابی موفق بود</div>
        <div class="card-subtitle" style="margin-bottom:32px;">
            دیتابیس پلتفرم با موفقیت بازیابی شد.
        </div>
        @if($message)
        <div class="alert alert-success" style="text-align:right;">
            ✅ {{ $message }}
        </div>
        @endif
        <div class="alert alert-info" style="text-align:right;">
            ℹ پلتفرم اکنون با داده‌های بازیابی‌شده در حال اجرا است. می‌توانید وارد پنل مدیریت شوید.
        </div>
    @elseif($success === false)
        {{-- Local source — sync failure --}}
        <div style="font-size:64px; margin-bottom:20px;">❌</div>
        <div class="card-title">بازیابی ناموفق بود</div>
        <div class="card-subtitle" style="margin-bottom:32px;">
            متأسفانه عملیات بازیابی دیتابیس با خطا مواجه شد.
        </div>
        @if($message)
        <div class="alert alert-danger" style="text-align:right;">
            ❌ {{ $message }}
        </div>
        @endif
        <div class="alert alert-warning" style="text-align:right;">
            ⚠ پلتفرم ممکن است در حالت maintenance باشد. دستور <code>php artisan up</code> را روی سرور اجرا کنید.
        </div>
        <div style="margin-top:16px;">
            <a href="{{ route('installer.step', ['mode'=>'restore','step'=>'source_select']) }}" class="btn btn-secondary">← بازگشت و تلاش مجدد</a>
        </div>
    @else
        {{-- Legacy / unknown state --}}
        <div style="font-size:64px; margin-bottom:20px;">♻️</div>
        <div class="card-title">بازیابی آغاز شد</div>
        <div class="card-subtitle" style="margin-bottom:32px;">
            عملیات بازیابی اجرا می‌شود.
        </div>
        <div class="alert alert-info" style="text-align:right;">
            ℹ برای مشاهده لاگ‌ها: پنل مدیریت → مرکز DR → لاگ‌های بازیابی
        </div>
    @endif

    <div style="margin-top:32px; display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">ورود به پنل مدیریت</a>
        @if($success)
            <a href="{{ route('installer.complete') }}" class="btn btn-secondary">مشاهده گزارش نصب</a>
        @endif
    </div>

</div>
@endsection
