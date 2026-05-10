@extends('layouts.auth-login')

@section('title', 'ورود')

@section('content')
<div class="auth-login-card">
    <h1>ورود به حساب</h1>
    <p class="auth-login-lead">موبایل و رمز عبور خود را وارد کنید.</p>

    @if ($errors->any())
        <div class="alert alert-danger text-sm">
            {{ $errors->first() }}
            @if($errors->has('captcha') && !empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha')
                <p class="mt-2 text-xs opacity-90">اگر روی دامنهٔ تست (مثلاً *.test) هستید: در پنل hCaptcha دامنه را اضافه کنید، یا در فایل <code>.env</code> مقدار <code>AUTH_CAPTCHA_SKIP_IN_LOCAL=true</code> بگذارید (فقط برای توسعه).</p>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="form-label" for="login-mobile">موبایل</label>
            <input id="login-mobile" type="text" name="mobile" value="{{ old('mobile') }}" placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹" class="form-control" required dir="rtl" autocomplete="tel">
        </div>

        <div>
            <label class="form-label" for="login-password">رمز عبور</label>
            <input id="login-password" type="password" name="password" placeholder="رمز عبور خود را وارد کنید" class="form-control" required dir="rtl" autocomplete="current-password">
        </div>

        <label class="auth-login-remember">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            <span>مرا به خاطر بسپار</span>
        </label>

        @include('partials.auth-captcha')

        <button type="submit" class="btn btn-primary w-full">ورود</button>

        <p class="auth-login-footer-text">
            حساب ندارید؟
            <a href="{{ route('register') }}">ثبت‌نام</a>
        </p>
    </form>
</div>
@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif
@endsection
