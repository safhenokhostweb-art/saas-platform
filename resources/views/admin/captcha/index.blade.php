@extends('layouts.admin')

@section('header', \App\Helpers\ModuleLabels::getTitle('captcha'))

@section('content')
@include('admin.captcha.guide')
<div class="max-w-2xl space-y-6">
    @if(!$moduleEnabled)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4">
            ماژول کپچا غیرفعال است. برای استفاده از کپچا، از منوی <a href="{{ route('admin.access.index') }}" class="underline">دسترسی و منو</a> ماژول «کپچا» را فعال کنید و سپس این صفحه را بروز کنید.
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">تنظیمات پیش‌فرض کپچا (پلتفرم)</h2>
        <p class="text-gray-500 text-sm mb-4">این تنظیمات به‌صورت پیش‌فرض برای فرم‌های سایت‌های مشتری استفاده می‌شود. هر سایت می‌تواند در تنظیمات خودش کپچا را فعال/غیرفعال کند و کلید hCaptcha جداگانه داشته باشد.</p>

        <form method="POST" action="{{ route('admin.captcha.update') }}" class="space-y-4">
            @csrf
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="captcha_enabled" value="1" class="form-check-input" {{ $captchaEnabled ? 'checked' : '' }}>
                <span>فعال‌سازی کپچا برای فرم‌های سایت‌ها (پیش‌فرض)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="auth_captcha_enabled" value="1" class="form-check-input" {{ $authCaptchaEnabled ?? false ? 'checked' : '' }}>
                <span>فعال‌سازی کپچا برای فرم ورود و ثبت‌نام</span>
            </label>
            <div>
                <label class="form-label">نوع کپچا</label>
                <select name="captcha_type" class="form-select w-full max-w-xs">
                    <option value="persian_number" {{ $captchaType === 'persian_number' ? 'selected' : '' }}>کپچای عدد فارسی</option>
                    <option value="normal" {{ $captchaType === 'normal' ? 'selected' : '' }}>کپچای معمولی (ریاضی فارسی)</option>
                    <option value="hcaptcha" {{ $captchaType === 'hcaptcha' ? 'selected' : '' }}>hCaptcha</option>
                </select>
            </div>
            <div>
                <label class="form-label">کلید سایت hCaptcha (اختیاری)</label>
                <input type="text" name="hcaptcha_site_key" class="form-control" value="{{ old('hcaptcha_site_key', $hcaptchaSiteKey) }}" placeholder="برای نوع hCaptcha وارد کنید">
            </div>
            <div>
                <label class="form-label">کلید مخفی hCaptcha (اختیاری)</label>
                <input type="password" name="hcaptcha_secret_key" class="form-control" value="{{ old('hcaptcha_secret_key', $hcaptchaSecretKey) }}" placeholder="برای نوع hCaptcha وارد کنید">
                <p class="text-gray-500 text-xs mt-1">برای تأیید پاسخ کاربر در سرور لازم است.</p>
            </div>
            @if($captchaType === 'hcaptcha')
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-sm text-blue-800">
                <strong>نکته برای hCaptcha:</strong> دامنهٔ سایت شما (مثلاً <code>{{ request()->getHost() }}</code>) باید در پنل hCaptcha در بخش «Passed Hostnames» یا «Domains» اضافه شود، وگرنه تأیید در سرور رد می‌شود. برای محیط لوکال (لاراگون با دامنه *.test) می‌توانید در فایل <code>.env</code> مقدار <code>AUTH_CAPTCHA_SKIP_IN_LOCAL=true</code> قرار دهید تا موقتاً تأیید رد نشود (فقط برای توسعه).
            </div>
            @endif
            <button type="submit" class="btn btn-primary">ذخیره</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">تنظیمات کپچا به‌ازای هر سایت</h2>
        <p class="text-gray-500 text-sm mb-4">برای هر سایت مشتری می‌توانید از صفحه ویرایش سایت یا لینک زیر، کپچا را جداگانه فعال/غیرفعال و نوع و کلید hCaptcha را تنظیم کنید.</p>
        <ul class="list-disc list-inside space-y-1">
            @foreach(\App\Models\Site::orderBy('title')->get() as $s)
                <li><a href="{{ route('admin.captcha.site', $s) }}" class="text-blue-600 hover:underline">{{ $s->title }}</a> ({{ $s->domain }})</li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
