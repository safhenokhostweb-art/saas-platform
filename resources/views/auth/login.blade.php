@extends('layouts.app')

@section('title','ورود')

@section('content')
<div class="flex justify-center px-4">
    <div class="card w-full max-w-md p-6">

        <h2 class="text-xl font-bold mb-6 text-center">
            ورود به حساب
        </h2>

        @if ($errors->any())
            <div class="alert alert-danger text-sm">
                {{ $errors->first() }}
                @if($errors->has('captcha') && !empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha')
                    <p class="mt-2 text-xs opacity-90">اگر روی دامنهٔ تست (مثلاً *.test) هستید: در پنل hCaptcha دامنه را اضافه کنید، یا در فایل <code>.env</code> پروژه مقدار <code>AUTH_CAPTCHA_SKIP_IN_LOCAL=true</code> بگذارید و کش را پاک کنید (فقط برای توسعه).</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="form-label">موبایل</label>
                <input type="text"
                       name="mobile"
                       value="{{ old('mobile') }}"
                       placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹"
                       class="form-control"
                       required dir="rtl">
            </div>

            <div>
                <label class="form-label">رمز عبور</label>
                <input type="password"
                       name="password"
                       placeholder="رمز عبور خود را وارد کنید"
                       class="form-control"
                       required dir="rtl">
            </div>

            @include('partials.auth-captcha')

            <button type="submit" class="btn btn-primary w-full">
                ورود
            </button>

            <p class="text-sm text-center mt-4">
                حساب نداری؟
                <a href="{{ route('register') }}" class="text-indigo-600 font-semibold hover:underline">
                    ثبت نام
                </a>
            </p>
        </form>
    </div>
</div>
@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif
@endsection
