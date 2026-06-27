@extends('layouts.admin')

@section('header', 'ویزارد نصب مشتری')

@push('head')
<style>
.wiz-step-nav{display:flex;align-items:center;gap:0;overflow-x:auto;padding-bottom:.5rem}
.wiz-step-item{display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.wiz-step-circle{width:2rem;height:2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;transition:all .2s;border:2px solid}
.wiz-step-circle--done{background:#4f46e5;border-color:#4f46e5;color:#fff}
.wiz-step-circle--active{background:#fff;border-color:#4f46e5;color:#4f46e5;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.wiz-step-circle--pending{background:#f3f4f6;border-color:#d1d5db;color:#9ca3af}
.wiz-step-label{font-size:.75rem;font-weight:500;transition:color .2s}
.wiz-step-label--active{color:#4f46e5;font-weight:700}
.wiz-step-label--done{color:#059669}
.wiz-step-label--pending{color:#9ca3af}
.wiz-sep{width:2rem;height:2px;background:#e2e8f0;flex-shrink:0;margin:0 .25rem}
.wiz-sep--done{background:#4f46e5}
.wiz-panel{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,0.05);padding:2rem}
.wiz-field label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem}
.wiz-server-card{border:2px solid #e2e8f0;border-radius:.875rem;padding:1rem;cursor:pointer;transition:all .2s}
.wiz-server-card:hover{border-color:#6366f1;background:#f5f3ff}
.wiz-server-card.selected{border-color:#4f46e5;background:#eef2ff}
.wiz-mode-card{border:2px solid #e2e8f0;border-radius:.875rem;padding:1rem 1.25rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.875rem}
.wiz-mode-card:hover{border-color:#6366f1;background:#f5f3ff}
.wiz-mode-card.selected{border-color:#4f46e5;background:#eef2ff}
.wiz-mode-icon{width:2.25rem;height:2.25rem;border-radius:.625rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.review-row{display:flex;align-items:baseline;gap:.5rem;padding:.5rem 0;border-bottom:1px solid #f1f5f9}
.review-label{font-size:.8rem;color:#6b7280;min-width:8rem;flex-shrink:0}
.review-value{font-size:.875rem;font-weight:500;color:#111827}
</style>
@endpush

@section('content')

<div x-data="installWizard()" x-init="init()">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
        <a href="{{ route('admin.infrastructure.customer-deployment.dashboard') }}" class="hover:text-indigo-600">استقرار مشتریان</a>
        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-800 font-medium">ویزارد نصب جدید</span>
    </div>

    {{-- Step Navigation --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6 overflow-x-auto">
        <div class="wiz-step-nav min-w-max">
            <template x-for="(step, idx) in steps" :key="idx">
                <div class="wiz-step-item">
                    <div :class="{'wiz-step-circle--done': currentStep > idx+1, 'wiz-step-circle--active': currentStep === idx+1, 'wiz-step-circle--pending': currentStep < idx+1}" class="wiz-step-circle">
                        <template x-if="currentStep > idx+1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="currentStep <= idx+1">
                            <span x-text="idx+1"></span>
                        </template>
                    </div>
                    <span :class="{'wiz-step-label--active': currentStep === idx+1, 'wiz-step-label--done': currentStep > idx+1, 'wiz-step-label--pending': currentStep < idx+1}" class="wiz-step-label hidden sm:block" x-text="step.label"></span>
                    <div x-show="idx < steps.length-1" :class="{'wiz-sep--done': currentStep > idx+1}" class="wiz-sep"></div>
                </div>
            </template>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.start') }}" id="installForm">
        @csrf

        {{-- Step 1: Customer Info --}}
        <div class="wiz-panel" x-show="currentStep === 1" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">اطلاعات مشتری</h2>
            <p class="text-sm text-gray-500 mb-6">مشخصات شرکت و مالک سایت مشتری را وارد کنید</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="wiz-field">
                    <label>نام شرکت / سازمان <span class="text-red-500">*</span></label>
                    <input type="text" name="customer_company" x-model="form.customer_company" class="form-control" placeholder="مثال: شرکت آلفا" required>
                </div>
                <div class="wiz-field">
                    <label>نام مالک <span class="text-red-500">*</span></label>
                    <input type="text" name="owner_name" x-model="form.owner_name" class="form-control" placeholder="نام کامل" required>
                </div>
                <div class="wiz-field">
                    <label>ایمیل <span class="text-red-500">*</span></label>
                    <input type="email" name="customer_email" x-model="form.customer_email" class="form-control" placeholder="info@example.com" required>
                </div>
                <div class="wiz-field">
                    <label>تلفن</label>
                    <input type="text" name="phone" x-model="form.phone" class="form-control" placeholder="09xxxxxxxxx">
                </div>
                <div class="wiz-field">
                    <label>زبان پیش‌فرض</label>
                    <select name="language" x-model="form.language" class="form-select">
                        <option value="fa">فارسی</option>
                        <option value="en">English</option>
                        <option value="ar">عربی</option>
                    </select>
                </div>
                <div class="wiz-field">
                    <label>منطقه زمانی</label>
                    <select name="timezone" x-model="form.timezone" class="form-select">
                        <option value="Asia/Tehran">تهران (UTC+3:30)</option>
                        <option value="Asia/Dubai">دبی (UTC+4)</option>
                        <option value="Europe/Istanbul">استانبول (UTC+3)</option>
                        <option value="UTC">UTC</option>
                    </select>
                </div>
                <div class="wiz-field">
                    <label>واحد پول</label>
                    <select name="currency" x-model="form.currency" class="form-select">
                        <option value="IRR">ریال ایران (IRR)</option>
                        <option value="IRT">تومان (IRT)</option>
                        <option value="USD">دلار (USD)</option>
                        <option value="EUR">یورو (EUR)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Step 2: Server Selection --}}
        <div class="wiz-panel" x-show="currentStep === 2" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">انتخاب سرور</h2>
            <p class="text-sm text-gray-500 mb-6">سرور مقصد برای نصب سایت مشتری را انتخاب کنید</p>

            @if($servers->isEmpty())
                <div class="text-center py-12 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M12 5l7 7-7 7"/></svg>
                    <p class="text-gray-500 mb-3">هنوز سرور SSH تعریف نشده</p>
                    <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}" class="btn btn-primary btn-sm" target="_blank">افزودن سرور</a>
                </div>
            @else
                <input type="hidden" name="remote_server_id" x-model="form.remote_server_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($servers as $server)
                    <div class="wiz-server-card" :class="form.remote_server_id == {{ $server->id }} ? 'selected' : ''" @click="form.remote_server_id = {{ $server->id }}">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold text-gray-900 text-sm">{{ $server->label }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $server->username }}@{{ $server->host }}:{{ $server->port }}</div>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">{{ strtoupper($server->type) }}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">{{ $server->auth_method === 'key' ? 'کلید SSH' : 'رمزعبور' }}</span>
                                    @if($server->wasTestedSuccessfully())
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-green-50 text-green-700 rounded text-xs">تست موفق</span>
                                    @endif
                                </div>
                            </div>
                            <div class="w-5 h-5 rounded-full border-2 mt-0.5 flex-shrink-0 transition-all" :class="form.remote_server_id == {{ $server->id }} ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300'">
                                <svg x-show="form.remote_server_id == {{ $server->id }}" class="w-full h-full text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-3">
                    <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}" class="text-indigo-600 hover:underline" target="_blank">افزودن سرور جدید</a>
                </p>
            @endif
        </div>

        {{-- Step 3: Domain & Install Mode --}}
        <div class="wiz-panel" x-show="currentStep === 3" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">دامنه و حالت نصب</h2>
            <p class="text-sm text-gray-500 mb-6">دامنه سایت مشتری و نوع نصب را مشخص کنید</p>

            <div class="wiz-field mb-6">
                <label>دامنه سایت <span class="text-red-500">*</span></label>
                <input type="text" name="customer_domain" x-model="form.customer_domain" class="form-control" placeholder="example.com" required>
                <p class="text-xs text-gray-400 mt-1">بدون https:// وارد کنید</p>
            </div>

            <div class="wiz-field mb-2">
                <label>حالت نصب</label>
            </div>
            <input type="hidden" name="install_mode" x-model="form.install_mode">
            <div class="grid grid-cols-1 gap-3">
                @foreach([
                    ['MODE_FRESH', 'نصب تمیز', 'نصب سایت جدید از صفر روی سرور مقصد', 'bg-indigo-50 text-indigo-600'],
                    ['MODE_RESTORE', 'بازیابی از بکاپ', 'بازیابی سایت از فایل بکاپ (بدون ساخت دیتابیس)', 'bg-green-50 text-green-600'],
                    ['MODE_CLONE', 'کلون از سایت موجود', 'کپی کامل از یک سایت موجود', 'bg-amber-50 text-amber-600'],
                    ['MODE_MOVE', 'انتقال به سرور', 'انتقال سایت از سرور فعلی به سرور جدید', 'bg-sky-50 text-sky-600'],
                    ['MODE_REINSTALL', 'نصب مجدد', 'نصب مجدد فایل‌های کد بدون تغییر دیتابیس', 'bg-red-50 text-red-600'],
                ] as [$mode, $title, $desc, $iconBg])
                <div class="wiz-mode-card" :class="form.install_mode === '{{ $mode }}' ? 'selected' : ''" @click="form.install_mode = '{{ $mode }}'">
                    <div class="wiz-mode-icon {{ $iconBg }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-semibold text-sm text-gray-900">{{ $title }}</div>
                        <div class="text-xs text-gray-500">{{ $desc }}</div>
                    </div>
                    <div class="w-5 h-5 rounded-full border-2 flex-shrink-0 transition-all" :class="form.install_mode === '{{ $mode }}' ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300'">
                        <svg x-show="form.install_mode === '{{ $mode }}'" class="w-full h-full text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Step 4: Database / Config --}}
        <div class="wiz-panel" x-show="currentStep === 4" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">پیکربندی</h2>
            <p class="text-sm text-gray-500 mb-6">اطلاعات اضافی پیکربندی سایت</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="wiz-field">
                    <label>سیاست بکاپ</label>
                    <select name="backup_policy" x-model="form.backup_policy" class="form-select">
                        <option value="daily">روزانه</option>
                        <option value="weekly">هفتگی</option>
                        <option value="none">بدون بکاپ خودکار</option>
                    </select>
                </div>
                <div class="wiz-field">
                    <label>SSL</label>
                    <div class="flex items-center gap-3 h-[38px]">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="ssl_enabled" value="1" x-model="form.ssl_enabled" class="rounded border-gray-300">
                            <span class="text-sm text-gray-700">فعال‌سازی SSL / HTTPS</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-5 p-4 bg-blue-50 rounded-xl border border-blue-100">
                <p class="text-sm text-blue-700 font-medium mb-1">اطلاعات دیتابیس خودکار</p>
                <p class="text-xs text-blue-600">نام دیتابیس، کاربر و رمزعبور به‌صورت تصادفی ایمن توسط موتور نصب تولید می‌شوند. نیازی به وارد کردن دستی نیست.</p>
            </div>
        </div>

        {{-- Step 5: License --}}
        <div class="wiz-panel" x-show="currentStep === 5" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">لایسنس</h2>
            <p class="text-sm text-gray-500 mb-6">نوع لایسنس سایت مشتری را انتخاب کنید</p>

            <input type="hidden" name="license_type" x-model="form.license_type">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach([
                    ['basic', 'پایه', 'برای کسب‌وکارهای کوچک', ['ماژول‌های پایه', 'پشتیبانی ایمیلی', 'بکاپ روزانه']],
                    ['professional', 'حرفه‌ای', 'برای کسب‌وکارهای متوسط', ['همه ماژول‌ها', 'پشتیبانی تلفنی', 'SLA 99.5%']],
                    ['enterprise', 'سازمانی', 'برای سازمان‌های بزرگ', ['همه ماژول‌ها + سفارشی', 'SLA 99.9%', 'پشتیبانی ۲۴/۷']],
                ] as [$type, $name, $desc, $features])
                <div class="border-2 rounded-xl p-5 cursor-pointer transition-all" :class="form.license_type === '{{ $type }}' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-200'" @click="form.license_type = '{{ $type }}'">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="font-bold text-gray-900">{{ $name }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $desc }}</div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 flex-shrink-0 transition-all mt-0.5" :class="form.license_type === '{{ $type }}' ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300'"></div>
                    </div>
                    <ul class="space-y-1.5">
                        @foreach($features as $f)
                        <li class="flex items-center gap-1.5 text-xs text-gray-600">
                            <svg class="w-3.5 h-3.5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            {{ $f }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Step 6: Theme (Placeholder) --}}
        <div class="wiz-panel" x-show="currentStep === 6" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">قالب (Theme)</h2>
            <p class="text-sm text-gray-500 mb-6">انتخاب قالب اولیه سایت مشتری</p>

            <input type="hidden" name="theme_slug" x-model="form.theme_slug">

            <div class="text-center py-16 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <svg class="w-14 h-14 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                </svg>
                <p class="text-gray-500 font-medium mb-2">Marketplace قالب در حال توسعه</p>
                <p class="text-sm text-gray-400">نصب بدون قالب انجام می‌شود. قالب را پس از نصب می‌توانید اعمال کنید.</p>
            </div>
        </div>

        {{-- Step 7: Modules --}}
        <div class="wiz-panel" x-show="currentStep === 7" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">ماژول‌ها</h2>
            <p class="text-sm text-gray-500 mb-6">ماژول‌های اولیه که هنگام نصب فعال می‌شوند</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach([
                    ['education', 'آموزش آنلاین', 'مدیریت دوره‌ها، مباحث، آزمون‌ها'],
                    ['tickets', 'تیکت پشتیبانی', 'سیستم تیکت‌گذاری مشتریان'],
                    ['forms', 'فرم‌ساز', 'ساخت فرم‌های سفارشی'],
                    ['ecommerce', 'فروشگاه', 'فروش محصولات و خدمات'],
                ] as [$mod, $name, $desc])
                <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-all hover:border-indigo-300 hover:bg-indigo-50">
                    <input type="checkbox" name="modules[]" value="{{ $mod }}" x-model="form.modules" class="rounded border-gray-300 mt-0.5">
                    <div>
                        <div class="font-medium text-sm text-gray-900">{{ $name }}</div>
                        <div class="text-xs text-gray-500">{{ $desc }}</div>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Step 8: Review & Install --}}
        <div class="wiz-panel" x-show="currentStep === 8" x-transition>
            <h2 class="text-lg font-bold text-gray-900 mb-1">بررسی نهایی</h2>
            <p class="text-sm text-gray-500 mb-6">قبل از شروع نصب، همه اطلاعات را بررسی کنید</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-2 border-b">اطلاعات مشتری</h3>
                    <div class="review-row"><span class="review-label">شرکت:</span><span class="review-value" x-text="form.customer_company || '—'"></span></div>
                    <div class="review-row"><span class="review-label">مالک:</span><span class="review-value" x-text="form.owner_name || '—'"></span></div>
                    <div class="review-row"><span class="review-label">ایمیل:</span><span class="review-value" x-text="form.customer_email || '—'"></span></div>
                    <div class="review-row"><span class="review-label">تلفن:</span><span class="review-value" x-text="form.phone || '—'"></span></div>
                    <div class="review-row"><span class="review-label">زبان:</span><span class="review-value" x-text="form.language"></span></div>
                    <div class="review-row"><span class="review-label">منطقه زمانی:</span><span class="review-value" x-text="form.timezone"></span></div>
                    <div class="review-row"><span class="review-label">ارز:</span><span class="review-value" x-text="form.currency"></span></div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-2 border-b">پیکربندی نصب</h3>
                    <div class="review-row"><span class="review-label">دامنه:</span><span class="review-value" x-text="form.customer_domain || '—'"></span></div>
                    <div class="review-row"><span class="review-label">حالت نصب:</span><span class="review-value" x-text="form.install_mode"></span></div>
                    <div class="review-row"><span class="review-label">سرور:</span><span class="review-value" x-text="selectedServerLabel || '—'"></span></div>
                    <div class="review-row"><span class="review-label">لایسنس:</span><span class="review-value" x-text="form.license_type"></span></div>
                    <div class="review-row"><span class="review-label">SSL:</span><span class="review-value" x-text="form.ssl_enabled ? 'فعال' : 'غیرفعال'"></span></div>
                    <div class="review-row"><span class="review-label">بکاپ:</span><span class="review-value" x-text="form.backup_policy"></span></div>
                    <div class="review-row"><span class="review-label">ماژول‌ها:</span><span class="review-value" x-text="form.modules.length ? form.modules.join('، ') : 'بدون ماژول'"></span></div>
                </div>
            </div>

            <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">تأیید شروع نصب</p>
                        <p class="text-xs text-amber-700 mt-0.5">پس از کلیک روی «شروع نصب»، فرآیند نصب در پس‌زمینه آغاز می‌شود و قابل لغو نیست. مطمئن شوید اطلاعات صحیح است.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Navigation Buttons --}}
        <div class="flex items-center justify-between mt-5">
            <button type="button" @click="prevStep()" x-show="currentStep > 1" class="btn btn-outline-secondary">
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                قبلی
            </button>
            <div x-show="currentStep <= 1"></div>

            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500" x-text="`مرحله ${currentStep} از ${steps.length}`"></span>
            </div>

            <div>
                <button type="button" @click="nextStep()" x-show="currentStep < steps.length" class="btn btn-primary">
                    بعدی
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="submit" x-show="currentStep === steps.length" :disabled="submitting" class="btn btn-primary" style="background:linear-gradient(135deg,#059669,#047857)">
                    <svg x-show="!submitting" class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="submitting" class="w-4 h-4 ml-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span x-text="submitting ? 'در حال ارسال...' : 'شروع نصب'"></span>
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function installWizard() {
    return {
        currentStep: 1,
        submitting: false,
        steps: [
            {label: 'مشتری'},
            {label: 'سرور'},
            {label: 'دامنه'},
            {label: 'پیکربندی'},
            {label: 'لایسنس'},
            {label: 'قالب'},
            {label: 'ماژول‌ها'},
            {label: 'بررسی'},
        ],
        form: {
            customer_company: '',
            owner_name: '',
            customer_email: '',
            phone: '',
            language: 'fa',
            timezone: 'Asia/Tehran',
            currency: 'IRR',
            customer_domain: '',
            install_mode: 'MODE_FRESH',
            remote_server_id: '',
            backup_policy: 'daily',
            ssl_enabled: false,
            license_type: 'basic',
            theme_slug: '',
            modules: [],
        },
        servers: @json($servers->map(fn($s) => ['id' => $s->id, 'label' => $s->label])),
        get selectedServerLabel() {
            const s = this.servers.find(s => s.id == this.form.remote_server_id);
            return s ? s.label : '';
        },
        init() {},
        nextStep() {
            if (!this.validateStep()) return;
            if (this.currentStep < this.steps.length) this.currentStep++;
        },
        prevStep() {
            if (this.currentStep > 1) this.currentStep--;
        },
        validateStep() {
            if (this.currentStep === 1) {
                if (!this.form.customer_company.trim()) { alert('نام شرکت الزامی است'); return false; }
                if (!this.form.owner_name.trim()) { alert('نام مالک الزامی است'); return false; }
                if (!this.form.customer_email.trim()) { alert('ایمیل الزامی است'); return false; }
            }
            if (this.currentStep === 2) {
                if (!this.form.remote_server_id) { alert('لطفاً یک سرور انتخاب کنید'); return false; }
            }
            if (this.currentStep === 3) {
                if (!this.form.customer_domain.trim()) { alert('دامنه الزامی است'); return false; }
            }
            return true;
        },
    };
}
document.getElementById('installForm').addEventListener('submit', function(e) {
    // Mark submitting state
});
</script>
@endpush

@endsection
