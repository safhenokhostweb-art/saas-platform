@extends('layouts.app')

@section('title','ثبت نام')

@section('content')
<div class="flex justify-center px-4">
    <div class="card w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4 text-center">ثبت نام</h2>

        @if ($errors->any())
            <div class="alert alert-danger text-sm mb-4">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label class="form-label">موبایل</label>
                <input type="text" name="mobile" placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹" class="form-control" required dir="rtl">
            </div>
            <div>
                <label class="form-label">رمز عبور</label>
                <input type="password" name="password" placeholder="حداقل ۶ کاراکتر" class="form-control" required dir="rtl">
            </div>
            <div>
                <label class="form-label">تکرار رمز عبور</label>
                <input type="password" name="password_confirmation" placeholder="تکرار رمز عبور" class="form-control" required dir="rtl">
            </div>

            @include('partials.auth-captcha')

            <button type="submit" class="btn btn-primary w-full">
                ثبت نام
            </button>
        </form>
    </div>
</div>
@if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
@push('scripts')
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
@endpush
@endif
@endsection
