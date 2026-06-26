@extends('installer.layout')
@section('title', 'بازیابی آغاز شد')

@section('content')
@include('installer.partials.progress')

<div class="card" style="text-align:center;">
    <div style="font-size:64px; margin-bottom:20px;">♻️</div>
    <div class="card-title">بازیابی در حال اجرا است</div>
    <div class="card-subtitle" style="margin-bottom:32px;">
        عملیات بازیابی به صف افزوده شد. می‌توانید پیشرفت را از مرکز DR پلتفرم دنبال کنید.
    </div>

    <div class="alert alert-info" style="text-align:right;">
        ℹ برای مشاهده لاگ‌های بازیابی، پس از تکمیل وارد پنل مدیریت → مرکز DR → لاگ‌های بازیابی شوید.
    </div>

    <div style="margin-top:32px; display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">ورود به پنل مدیریت</a>
    </div>
</div>
@endsection
