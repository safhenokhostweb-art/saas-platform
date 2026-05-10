@extends('layouts.auth-login')

@section('title', 'ثبت‌نام')

@section('content')
<div class="auth-login-card">
    <h1>ثبت‌نام</h1>
    <p class="auth-login-lead">یک حساب کاربری جدید بسازید.</p>

    @if ($errors->any())
        <div class="alert alert-danger text-sm mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('register.submit') }}" class="space-y-4">
        @csrf

        <div>
            <label class="form-label" for="reg-mobile">موبایل</label>
            <input id="reg-mobile" type="text" name="mobile" value="{{ old('mobile') }}" placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹" class="form-control" required dir="rtl" autocomplete="tel">
        </div>
        <div>
            <label class="form-label" for="reg-password">رمز عبور</label>
            <input id="reg-password" type="password" name="password" placeholder="حداقل ۶ کاراکتر" class="form-control" required dir="rtl" autocomplete="new-password">
        </div>
        <div>
            <label class="form-label" for="reg-password-confirm">تکرار رمز عبور</label>
            <input id="reg-password-confirm" type="password" name="password_confirmation" placeholder="تکرار رمز عبور" class="form-control" required dir="rtl" autocomplete="new-password">
        </div>

        @include('partials.auth-captcha')

        <button type="submit" class="btn btn-primary w-full">ثبت‌نام</button>

        <p class="auth-login-footer-text">
            قبلاً ثبت‌نام کرده‌اید؟
            <a href="{{ route('login.show') }}">ورود</a>
        </p>
    </form>
</div>
@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif
@endsection
