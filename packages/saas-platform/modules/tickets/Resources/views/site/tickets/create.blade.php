@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'ارسال تیکت جدید')

@push('head')
<style>
.form-glass-card{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.form-glass-header{background:linear-gradient(135deg,rgba(99,102,241,0.06),rgba(168,85,247,0.04));border-bottom:1px solid rgba(255,255,255,0.2);border-radius:1.25rem 1.25rem 0 0;padding:1.25rem 1.75rem}
.form-input{width:100%!important;padding:0.75rem 1rem!important;border:1px solid #e2e8f0!important;border-radius:0.75rem!important;font-size:0.875rem!important;transition:all 0.2s ease;background:rgba(255,255,255,0.6)}
.form-input:focus{outline:none!important;border-color:#a5b4fc!important;box-shadow:0 0 0 3px rgba(99,102,241,0.12)!important}
.form-input:hover{border-color:#c7d2fe!important}
.form-label{display:block!important;font-size:0.8125rem!important;font-weight:600!important;color:#374151!important;margin-bottom:0.5rem!important}
.form-label .required{color:#ef4444;margin-right:2px}
.on-behalf-panel{background:linear-gradient(135deg,rgba(99,102,241,0.05),rgba(168,85,247,0.03));border:1px solid rgba(99,102,241,0.15);border-radius:1rem;padding:1rem 1.25rem}
</style>
@endpush

@section('content')
@php
    // Section K: Feature Request — این view برای ادمین سایت مشتری است.
    // ادمین سایت می‌تواند هم تیکت پشتیبانی و هم درخواست ویژگی (FR) ارسال کند.
    // کاربران عادی (end user) فقط تیکت پشتیبانی ارسال می‌کنند.
    $canSubmitFR = (bool) (auth()->user()?->is_site_admin ?? isset($isSiteAdminContext));
    $frNotice = \App\Models\Setting::get('tickets.fr_customer_notice',
        'این درخواست صرفاً به عنوان پیشنهاد دریافت می‌گردد و پس از امکان‌سنجی، بررسی فنی و تأیید نهایی، نتیجه از طریق همین تیکت یا سایر کانال‌های اطلاع‌رسانی به شما اعلام خواهد شد.'
    );
@endphp
<div class="max-w-3xl mx-auto px-4 py-6"
     x-data="{
         ticketType: '{{ old('type', 'support') }}',
         users: {{ json_encode($users->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'mobile' => $u->mobile ?? ''])->values()) }},
         selectedUserId: '{{ old('for_user_id') }}',
         get selectedUser() { return this.users.find(u => u.id == this.selectedUserId) ?? null; }
     }">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('tickets.site.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900"
                x-text="ticketType === 'feature_request' ? 'ارسال درخواست ویژگی' : 'ارسال تیکت پشتیبانی'">
                ارسال تیکت پشتیبانی
            </h2>
            <p class="text-sm text-gray-500 mt-0.5"
               x-text="ticketType === 'feature_request' ? 'پیشنهاد بهبود یا ویژگی جدید برای پلتفرم' : 'مشکل یا سوال خود را با ما در میان بگذارید'">
                مشکل یا سوال خود را با ما در میان بگذارید
            </p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-6 bg-red-50/85 border border-red-200 rounded-2xl p-4"
         x-data="{ show: true }" x-show="show" x-transition>
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-800 mb-1">{{ count($errors->all()) }} خطا در ارسال فرم</p>
                <ul class="list-disc list-inside space-y-0.5 text-sm text-red-700">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            <button @click="show = false" class="text-red-400 hover:text-red-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('tickets.site.store') }}"
          enctype="{{ $attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}"
          class="form-glass-card overflow-hidden">
        @csrf

        <div class="form-glass-header">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg text-lg">🎫</span>
                <div>
                    <h3 class="font-bold text-gray-900">اطلاعات تیکت</h3>
                    <p class="text-xs text-gray-500 mt-0.5">تمام فیلدهای الزامی را پر کنید</p>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-8 space-y-6">

            {{-- ═══ Section K: نوع تیکت — فقط برای ادمین سایت ═══ --}}
            @if($canSubmitFR)
            <div>
                <label class="form-label">نوع درخواست</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="support" x-model="ticketType" class="sr-only">
                        <div :class="ticketType === 'support' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                             class="flex items-center gap-3 p-3.5 rounded-xl border-2 transition-all">
                            <div class="w-8 h-8 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">پشتیبانی</p>
                                <p class="text-[10px] text-gray-500">مشکل یا سوال فنی</p>
                            </div>
                            <div :class="ticketType === 'support' ? 'bg-indigo-500' : 'bg-gray-200'"
                                 class="w-4 h-4 rounded-full mr-auto flex items-center justify-center transition-colors shrink-0">
                                <div x-show="ticketType === 'support'" class="w-2 h-2 rounded-full bg-white"></div>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="feature_request" x-model="ticketType" class="sr-only">
                        <div :class="ticketType === 'feature_request' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                             class="flex items-center gap-3 p-3.5 rounded-xl border-2 transition-all">
                            <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">درخواست ویژگی</p>
                                <p class="text-[10px] text-gray-500">پیشنهاد بهبود به SaaS</p>
                            </div>
                            <div :class="ticketType === 'feature_request' ? 'bg-purple-500' : 'bg-gray-200'"
                                 class="w-4 h-4 rounded-full mr-auto flex items-center justify-center transition-colors shrink-0">
                                <div x-show="ticketType === 'feature_request'" class="w-2 h-2 rounded-full bg-white"></div>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- Section M: Notice هنگام انتخاب درخواست ویژگی --}}
                <div x-show="ticketType === 'feature_request'" x-transition
                     class="mt-3 bg-amber-50 border border-amber-200 rounded-xl p-3"
                     style="display:none">
                    <p class="text-xs text-amber-800 leading-relaxed">{{ $frNotice }}</p>
                </div>
            </div>
            @else
            <input type="hidden" name="type" value="support">
            @endif

            {{-- ═══ ثبت برای کاربر ═══ --}}
            @if($users->isNotEmpty())
            <div class="on-behalf-panel">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-sm font-semibold text-indigo-800">ثبت تیکت برای کاربر مشخص</span>
                    <span class="text-xs text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full">اختیاری</span>
                </div>
                <select name="for_user_id" x-model="selectedUserId" class="form-input">
                    <option value="">— انتخاب کاربر (اختیاری) —</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(old('for_user_id') == $u->id)>
                        {{ $u->name }}{{ $u->mobile ? ' — ' . $u->mobile : '' }}
                    </option>
                    @endforeach
                </select>
                <p class="text-xs text-indigo-400 mt-2">با انتخاب کاربر، ایمیل و نام به‌صورت خودکار تکمیل می‌شود.</p>
            </div>
            @endif

            {{-- موضوع --}}
            <div>
                <label class="form-label">موضوع <span class="required">*</span></label>
                <input type="text" name="subject" value="{{ old('subject') }}" required
                       class="form-input @error('subject') !border-red-400 @enderror"
                       placeholder="موضوع تیکت را وارد کنید">
                @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- اولویت + دسته‌بندی --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="form-label">اولویت</label>
                    <select name="priority" class="form-input">
                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>➡️ عادی</option>
                        <option value="high"   @selected(old('priority') === 'high')>🔴 بالا</option>
                        <option value="low"    @selected(old('priority') === 'low')>🔽 کم</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">دسته‌بندی <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                    <select name="category" class="form-input">
                        <option value="">— انتخاب کنید —</option>
                        <option value="technical" @selected(old('category') === 'technical')>🔧 فنی</option>
                        <option value="billing"   @selected(old('category') === 'billing')>💰 مالی</option>
                        <option value="feature"   @selected(old('category') === 'feature')>✨ درخواست ویژگی</option>
                        <option value="bug"       @selected(old('category') === 'bug')>🐛 گزارش مشکل</option>
                        <option value="other"     @selected(old('category') === 'other')>📋 سایر</option>
                    </select>
                </div>
            </div>

            {{-- نام + ایمیل مشتری --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="form-label">نام مشتری <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                    <input type="text" name="name"
                           :value="selectedUser ? selectedUser.name : '{{ old('name') }}'"
                           :readonly="!!selectedUser"
                           :class="selectedUser ? '!bg-indigo-50/50 !border-indigo-200 !text-indigo-700' : ''"
                           class="form-input" placeholder="مثال: علی رضایی">
                </div>
                <div>
                    <label class="form-label">موبایل مشتری <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                    <input type="text" name="mobile"
                           :value="selectedUser ? selectedUser.mobile : '{{ old('mobile') }}'"
                           :readonly="!!selectedUser"
                           :class="selectedUser ? '!bg-indigo-50/50 !border-indigo-200 !text-indigo-700' : ''"
                           class="form-input" placeholder="09xxxxxxxxx" dir="ltr">
                </div>
            </div>

            {{-- پیام --}}
            <div>
                <label class="form-label">توضیحات <span class="required">*</span></label>
                <textarea name="message" rows="6" required
                          class="form-input resize-y min-h-[140px] @error('message') !border-red-400 @enderror @error('description') !border-red-400 @enderror"
                          placeholder="متن کامل مشکل یا سوال خود را بنویسید...">{{ old('message', old('description')) }}</textarea>
                @error('message')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-gray-400 mt-1">💡 توضیحات کامل مشکل را بنویسید — حداکثر ۵۰۰۰ کاراکتر</p>
            </div>

            {{-- پیوست --}}
            @if($attachmentsEnabled)
            <div>
                <label class="form-label">پیوست فایل <span class="text-gray-400 text-xs">(اختیاری)</span></label>
                <input type="file" name="attachments[]" multiple
                       class="form-input file:!rounded-lg file:!border-0 file:!bg-indigo-50 file:!text-indigo-700 file:!px-4 file:!py-2 file:!text-sm file:!font-medium hover:file:!bg-indigo-100"
                       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip">
                <p class="text-[10px] text-gray-400 mt-1">حداکثر ۱۰ مگابایت — فرمت‌های مجاز: jpg, png, pdf, doc, zip</p>
            </div>
            @endif

        </div>

        <div class="flex items-center justify-between gap-4 px-6 sm:px-8 py-4 border-t border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                اطلاعات با امنیت ذخیره می‌شود
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tickets.site.index') }}" class="btn-ghost">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    انصراف
                </a>
                <button type="submit" class="btn-gradient">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    ارسال تیکت
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
