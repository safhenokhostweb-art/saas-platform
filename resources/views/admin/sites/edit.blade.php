@extends('layouts.admin')

@section('header', 'ویرایش سایت')

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <h4 class="font-bold mb-4 text-xl">ویرایش سایت: {{ $site->title }}</h4>

    @if ($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            <ul class="list-disc list-inside text-right mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- GAP-L09: Post-create checklist shown only immediately after site creation --}}
    @if(session('just_created'))
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 mb-5 relative" dir="rtl">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-indigo-900 text-sm mb-3">سایت ایجاد شد — مراحل بعدی را تکمیل کنید</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <a href="{{ route('admin.sites.lifecycle.index', $site) }}"
                       class="flex items-center gap-2 text-xs text-indigo-700 bg-white border border-indigo-200 rounded-lg px-3 py-2 hover:bg-indigo-50 transition">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        بررسی چرخه عمر سایت
                    </a>
                    <a href="{{ route('admin.sites.edit', $site) }}#modules"
                       class="flex items-center gap-2 text-xs text-indigo-700 bg-white border border-indigo-200 rounded-lg px-3 py-2 hover:bg-indigo-50 transition">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        فعال‌سازی ماژول‌ها
                    </a>
                    <a href="{{ route('admin.subscriptions.index') }}?site={{ $site->id }}"
                       class="flex items-center gap-2 text-xs text-indigo-700 bg-white border border-indigo-200 rounded-lg px-3 py-2 hover:bg-indigo-50 transition">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        تنظیم اشتراک
                    </a>
                    <a href="{{ route('admin.resources.sites.index') }}"
                       class="flex items-center gap-2 text-xs text-indigo-700 bg-white border border-indigo-200 rounded-lg px-3 py-2 hover:bg-indigo-50 transition">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        تنظیم منابع و محدودیت‌ها
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.sites.update', $site) }}" class="card p-4 shadow-sm rounded-xl border-0">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label">مشتری</label>
            <select name="customer_id" class="form-select" required>
                <option value="">انتخاب مشتری</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" {{ old('customer_id', $site->customer_id) == $customer->id ? 'selected' : '' }}>
                        {{ $customer->company_name ?? $customer->name ?? $customer->email ?? 'بدون نام' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">عنوان سایت</label>
            <input type="text" name="title" class="form-control" placeholder="نام یا عنوان سایت" value="{{ old('title', $site->title) }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label">دامنه</label>
            <input type="text" name="domain" class="form-control" placeholder="مثال: example.com" value="{{ old('domain', $site->domain) }}">
        </div>

        @if(isset($paymentGateways) && $paymentGateways->isNotEmpty() && $site->hasBankGatewayModule())
        <div class="mb-3">
            <label class="form-label">درگاه پرداخت (اختیاری)</label>
            <select name="payment_gateway_id" class="form-select">
                <option value="">استفاده از درگاه پیش‌فرض پلتفرم</option>
                @foreach($paymentGateways as $gw)
                    <option value="{{ $gw->id }}" {{ old('payment_gateway_id', $site->payment_gateway_id) == $gw->id ? 'selected' : '' }}>{{ $gw->name }} ({{ \App\Models\PaymentGateway::drivers()[$gw->driver] ?? $gw->driver }})</option>
                @endforeach
            </select>
            <p class="text-sm text-gray-500 mt-1">فقط برای سایت‌هایی که ماژول «درگاه بانکی» دارند نمایش داده می‌شود.</p>
        </div>
        @endif

        @if(\Illuminate\Support\Facades\Schema::hasTable('themes') && \Illuminate\Support\Facades\Schema::hasColumn('sites', 'theme_id') && isset($themes) && $themes->isNotEmpty())
        <div class="mb-4">
            <label class="form-label">تم (Theme Builder)</label>
            <select name="theme_id" class="form-select">
                <option value="">بدون تم (قالب پیش‌فرض پلتفرم)</option>
                @foreach($themes as $t)
                    <option value="{{ $t->id }}" {{ old('theme_id', $site->theme_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
            <p class="text-sm text-gray-500 mt-1">تم انتخاب‌شده هدر، فوتر و استایل سراسری سایت را تعیین می‌کند. @can('accessWizard')<a href="{{ route('admin.theme-builder.index') }}" class="text-indigo-600 hover:underline">ساختار تم</a>@elseساختار تم@endcan می‌توانید تم جدید بسازید یا ویرایش کنید (فقط Super Admin).</p>
        </div>
        @endif

        <hr>

        <div id="site-access" class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <h6 class="font-bold text-blue-900 mb-1">دسترسی و ماژول‌های این سایت (مشتری)</h6>
            <p class="text-sm text-blue-800 mb-3">ماژول‌های فعال برای این سایت را انتخاب کنید. فقط ماژول‌هایی که در منوی «دسترسی و منو» فعال کرده‌اید اینجا نمایش داده می‌شوند.</p>
            @forelse($modules as $module)
                @php $siteMod = $site->modules->firstWhere('id', $module->id); $isActive = $siteMod && $siteMod->pivot->is_active; @endphp
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $module->id }}"
                        {{ $isActive ? 'checked' : '' }}>
                    <label class="form-check-label">
                        {{ $module->title }}
                    </label>
                </div>
            @empty
                <p class="text-gray-600 text-sm">ماژولی تعریف نشده یا در <a href="{{ route('admin.access.index') }}" class="underline">دسترسی و منو</a> هیچ ماژولی فعال نیست.</p>
            @endforelse
        </div>

        <hr>

        @if(\Illuminate\Support\Facades\Schema::hasTable('site_settings'))
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
            <input type="hidden" name="site_design_section" value="1">
            <h6 class="font-bold text-amber-900 mb-2">تنظیمات ظاهر فرانت (اختیاری)</h6>
            <p class="text-sm text-amber-800 mb-3">در صورت خالی گذاشتن، از تنظیمات عمومی پلتفرم استفاده می‌شود.</p>
            @php
                $colorPalette = ['#4f46e5','#2563eb','#059669','#dc2626','#ea580c','#ca8a04','#7c3aed','#db2777','#0d9488','#1e40af','#374151','#ffffff'];
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-sm">رنگ اصلی</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" class="h-9 w-12 rounded border cursor-pointer" value="{{ old('site_design_theme_primary', $siteDesign['theme_primary'] ?? '#4f46e5') }}" onchange="this.nextElementSibling.value=this.value">
                        <input type="text" name="site_design_theme_primary" class="form-control flex-1" value="{{ old('site_design_theme_primary', $siteDesign['theme_primary'] ?? '') }}" placeholder="#4f46e5">
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($colorPalette as $c)
                            <button type="button" class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition" style="background:{{ $c }}" title="{{ $c }}" onclick="var inp=this.closest('div').previousElementSibling.querySelector('input[type=text]'); var col=this.closest('div').previousElementSibling.querySelector('input[type=color]'); inp.value='{{ $c }}'; col.value='{{ $c }}';"></button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="form-label text-sm">پس‌زمینه منو</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" class="h-9 w-12 rounded border cursor-pointer" value="{{ old('site_design_theme_menu_bg', $siteDesign['theme_menu_bg'] ?? '#ffffff') }}" onchange="this.nextElementSibling.value=this.value">
                        <input type="text" name="site_design_theme_menu_bg" class="form-control flex-1" value="{{ old('site_design_theme_menu_bg', $siteDesign['theme_menu_bg'] ?? '') }}" placeholder="#ffffff">
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($colorPalette as $c)
                            <button type="button" class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition" style="background:{{ $c }}" title="{{ $c }}" onclick="var d=this.closest('div').previousElementSibling; var inp=d.querySelector('input[type=text]'); var col=d.querySelector('input[type=color]'); inp.value='{{ $c }}'; col.value='{{ $c }}';"></button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="form-label text-sm">رنگ متن منو</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" class="h-9 w-12 rounded border cursor-pointer" value="{{ old('site_design_theme_menu_text', $siteDesign['theme_menu_text'] ?? '#374151') }}" onchange="this.nextElementSibling.value=this.value">
                        <input type="text" name="site_design_theme_menu_text" class="form-control flex-1" value="{{ old('site_design_theme_menu_text', $siteDesign['theme_menu_text'] ?? '') }}" placeholder="#374151">
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($colorPalette as $c)
                            <button type="button" class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition" style="background:{{ $c }}" title="{{ $c }}" onclick="var d=this.closest('div').previousElementSibling; var inp=d.querySelector('input[type=text]'); var col=d.querySelector('input[type=color]'); inp.value='{{ $c }}'; col.value='{{ $c }}';"></button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="form-label text-sm">لوگوی اختصاصی سایت</label>
                    <input type="text" name="site_design_site_logo" class="form-control" value="{{ old('site_design_site_logo', $siteDesign['site_logo'] ?? '') }}" placeholder="مسیر یا URL تصویر (خالی = لوگوی پلتفرم)">
                </div>
            </div>
        </div>
        <hr>
        @endif

        <h6 class="font-bold mt-3">اطلاعات هاست و دیتابیس</h6>

        @if(!$site->db_host || !$site->db_name)
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3 p-3" style="border-radius:8px; background:#fffbeb; border:1px solid #fcd34d;">
            <span style="font-size:1.1rem;">⚠️</span>
            <div>
                <strong>برای نصب قالب روی این سایت، اطلاعات دیتابیس الزامی است.</strong><br>
                <small class="text-muted">این اطلاعات را از فایل <code>.env</code> سایت مشتری (<code>DB_HOST</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>) بخوانید و اینجا وارد کنید.</small>
            </div>
        </div>
        @endif

        <input name="hosting_company" class="form-control mb-2" placeholder="نام شرکت هاست" value="{{ old('hosting_company', $site->hosting_company) }}">
        <input name="db_host" class="form-control mb-2" placeholder="آدرس سرور دیتابیس — مثلاً: 127.0.0.1" value="{{ old('db_host', $site->db_host) }}">
        <input name="db_name" class="form-control mb-2" placeholder="نام دیتابیس — مثلاً: customer_site" value="{{ old('db_name', $site->db_name) }}">
        <input name="db_user" class="form-control mb-2" placeholder="کاربر دیتابیس — مثلاً: root" value="{{ old('db_user', $site->db_user) }}">
        {{-- Phase 13-O12c — write-only: never pre-fill the current password (encrypted or legacy
             plaintext) into the DOM. Leaving this empty on submit keeps the stored credential unchanged. --}}
        <input name="db_password" class="form-control mb-2" placeholder="رمز عبور دیتابیس — خالی بگذارید تا رمز فعلی حفظ شود" value="{{ old('db_password') }}" type="password" autocomplete="new-password">
        <small class="text-muted d-block mb-2">برای تغییر رمز عبور دیتابیس، مقدار جدید را وارد کنید. خالی گذاشتن این فیلد، رمز عبور فعلی را دست‌نخورده نگه می‌دارد.</small>

        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای هاست (شمسی)</label>
                <x-persian-date-input
                    name="host_expires_at"
                    :value="old('host_expires_at', $site->host_expires_at ? \App\Helpers\Jalali::format($site->host_expires_at) : '')"
                    placeholder="۱۴۰۳/۰۷/۱۵"
                />
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای دامنه (شمسی)</label>
                <x-persian-date-input
                    name="domain_expires_at"
                    :value="old('domain_expires_at', $site->domain_expires_at ? \App\Helpers\Jalali::format($site->domain_expires_at) : '')"
                    placeholder="۱۴۰۳/۰۷/۱۵"
                />
            </div>
        </div>

        <hr>

        <div class="mb-3">
            <label class="form-label">تاریخ انقضای پشتیبانی (شمسی)</label>
            <x-persian-date-input
                name="support_expires_at"
                :value="old('support_expires_at', \App\Helpers\Jalali::format($site->support_expires_at))"
                placeholder="۱۴۰۳/۰۷/۱۵"
            />
            @php $supportProduct = \App\Models\Product::where('is_active', true)->where('type', \App\Models\Product::TYPE_SUPPORT_PLAN)->first(); @endphp
            @if($supportProduct)
                <p class="text-sm text-gray-600 mt-1">
                    <a href="{{ route('store.index') }}?site_id={{ $site->id }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                        خرید پشتیبانی برای این سایت →
                    </a>
                </p>
            @endif
        </div>

        <div class="mb-3">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $site->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>سایت فعال است</span>
            </label>
        </div>

        <div class="flex gap-2 flex-wrap items-center">
            <button type="submit" class="btn btn-primary">
                ذخیره تغییرات
            </button>
            <a href="{{ route('admin.sites.index') }}" class="btn btn-secondary">انصراف</a>
            <a href="{{ route('admin.captcha.site', $site) }}" class="btn btn-outline-primary">تنظیمات کپچا این سایت</a>
            <a href="{{ route('admin.sites.modules.edit', $site) }}" class="btn btn-outline-primary">ماژول‌های این سایت</a>
            <a href="{{ route('admin.sites.asset-access', $site) }}" class="btn btn-outline-primary">قالب‌ها و ویجت‌های اختصاصی</a>
            @if(\App\Helpers\PlatformModules::isEnabled('notifications'))
            <a href="{{ route('admin.sites.notifications.index', $site) }}" class="btn btn-outline-primary">اعلان‌های این سایت</a>
            @endif
            <a href="{{ route('admin.sites.updates.index', $site) }}" class="btn btn-outline-primary">توزیع بروزرسانی</a>
            <a href="{{ route('admin.sites.agents.show', $site) }}" class="btn btn-outline-primary" style="border-color:#6366f1;color:#4f46e5">
                🔑 ادمین پلتفرم
            </a>
            <a href="{{ route('admin.access.index') }}" class="btn btn-outline-secondary">دسترسی و منو (ماژول‌های پلتفرم)</a>
        </div>
    </form>
</div>

@push('head')
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
@endpush
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
@endpush
@endsection
