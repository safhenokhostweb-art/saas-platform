@extends('installer.layout')
@section('title', 'ارتقاء تکمیل شد')

@section('content')
@include('installer.partials.progress')

<div class="card" style="text-align:center;">
    <div style="font-size:64px; margin-bottom:20px;">⬆️✅</div>
    <div class="card-title">ارتقاء با موفقیت انجام شد</div>
    <div class="card-subtitle">پلتفرم به نسخه جدید ارتقاء یافت و سالم است.</div>

    <div style="margin-top:32px; display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">ورود به پنل مدیریت</a>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">داشبورد مدیریت</a>
    </div>
</div>
@endsection
