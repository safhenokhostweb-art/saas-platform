@extends(config('tickets.layouts.admin', 'admin.layout'))

@section('title', 'تنظیمات ماژول پشتیبانی')

@push('head')
<style>
.glass-card{background:rgba(255,255,255,0.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.3);border-radius:1.25rem;box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.form-input{width:100%!important;padding:0.65rem 0.875rem!important;border:1px solid #e2e8f0!important;border-radius:0.75rem!important;font-size:0.875rem!important;transition:all 0.2s ease;background:rgba(255,255,255,0.7)}
.form-input:focus{outline:none!important;border-color:#a5b4fc!important;box-shadow:0 0 0 3px rgba(99,102,241,0.12)!important}
.tab-btn{display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1.25rem;border-radius:0.75rem;font-size:0.875rem;font-weight:600;cursor:pointer;transition:all 0.2s ease;white-space:nowrap;border:none;background:transparent;color:#64748b}
.tab-btn.active,.tab-btn[aria-selected="true"]{background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;box-shadow:0 4px 14px rgba(99,102,241,0.3)}
.tab-btn:not(.active):hover{background:#f1f5f9;color:#475569}
.color-preview{width:2rem;height:2rem;border-radius:0.5rem;border:1px solid #e2e8f0;cursor:pointer}
.toggle-pill{position:relative;display:inline-block;width:3rem;height:1.75rem;cursor:pointer}
.toggle-pill input{opacity:0;width:0;height:0;position:absolute}
.toggle-pill .slider{position:absolute;inset:0;border-radius:9999px;background:#cbd5e1;transition:all 0.3s ease}
.toggle-pill input:checked+.slider{background:linear-gradient(135deg,#6366f1,#a855f7)}
.toggle-pill .slider::before{content:'';position:absolute;width:1.25rem;height:1.25rem;left:0.25rem;top:0.25rem;border-radius:50%;background:#fff;transition:transform 0.3s ease;box-shadow:0 1px 4px rgba(0,0,0,0.15)}
.toggle-pill input:checked+.slider::before{transform:translateX(1.25rem)}
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ tab: 'appearance' }">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('tickets.admin.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">تنظیمات ماژول پشتیبانی</h2>
            <p class="text-sm text-gray-500 mt-1">ظاهر چت، رفتار پیش‌فرض و تنظیمات پیامک</p>
        </div>
    </div>

    @if(session('success'))
    <div class="glass-card mb-6 p-4 border-r-4 border-r-green-400 flex items-center gap-3"
         x-data="{ show: true }" x-show="show" x-transition>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium">{{ session('success') }}</span>
        <button @click="show = false" class="mr-auto text-green-400 hover:text-green-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl">
        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Tab bar --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-5">
        <button class="tab-btn" :class="{ 'active': tab === 'appearance' }" @click="tab = 'appearance'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            ظاهر چت
        </button>
        <button class="tab-btn" :class="{ 'active': tab === 'behavior' }" @click="tab = 'behavior'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            رفتار پیش‌فرض
        </button>
        <button class="tab-btn" :class="{ 'active': tab === 'sms' }" @click="tab = 'sms'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            پیامک
        </button>
        <button class="tab-btn" :class="{ 'active': tab === 'templates' }" @click="tab = 'templates'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
            قالب‌های پیامک
        </button>
    </div>

    <form method="POST" action="{{ route('tickets.admin.settings.update') }}">
        @csrf @method('PATCH')

        {{-- ═══ TAB: Appearance ═══════════════════════════════════════════ --}}
        <div x-show="tab === 'appearance'" x-transition>
            <div class="glass-card p-6">
                <h3 class="font-bold text-gray-800 mb-5 flex items-center gap-2">
                    <span class="w-8 h-8 bg-gradient-to-r from-pink-400 to-purple-500 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                    </span>
                    رنگ‌بندی حباب‌های چت
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                    {{-- Customer bubble --}}
                    <div x-data="{ color: '{{ $settings['chat_customer_bg'] }}' }">
                        <p class="text-sm font-semibold text-gray-700 mb-3">حباب مشتری</p>
                        <div class="flex items-center gap-3 mb-3">
                            <div :style="`background:${color};`" class="w-32 rounded-2xl rounded-br-sm px-4 py-3 shadow-sm border border-gray-200">
                                <p class="text-xs font-semibold text-gray-600 mb-1">سلام</p>
                                <p class="text-sm" :style="`color: '{{ $settings['chat_customer_text'] }}'`">پیام مشتری نمونه</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" name="chat_customer_bg" x-model="color"
                                   value="{{ $settings['chat_customer_bg'] }}"
                                   class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                            <div class="flex-1">
                                <label class="text-xs text-gray-500 mb-1 block">رنگ پس‌زمینه</label>
                                <input type="text" x-model="color" class="form-input font-mono text-xs" placeholder="#e3f2fd" dir="ltr">
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mt-3">
                            <input type="color" name="chat_customer_text" value="{{ $settings['chat_customer_text'] }}"
                                   class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                            <div class="flex-1">
                                <label class="text-xs text-gray-500 mb-1 block">رنگ متن</label>
                                <input type="text" name="chat_customer_text_display" value="{{ $settings['chat_customer_text'] }}" class="form-input font-mono text-xs" placeholder="#1a1a2e" dir="ltr"
                                       x-ref="custText" @input="$el.previousElementSibling.previousElementSibling.value = $el.value">
                                {{-- hidden real field --}}
                                <input type="hidden" name="chat_customer_text" value="{{ $settings['chat_customer_text'] }}"
                                       x-ref="custTextHidden">
                            </div>
                        </div>
                    </div>

                    {{-- Staff bubble --}}
                    <div x-data="{ color: '{{ $settings['chat_staff_bg'] }}' }">
                        <p class="text-sm font-semibold text-gray-700 mb-3">حباب کارشناس</p>
                        <div class="flex items-center gap-3 mb-3">
                            <div :style="`background:${color};`" class="w-32 rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm border border-gray-200 self-end mr-auto">
                                <p class="text-xs font-semibold text-gray-600 mb-1">کارشناس</p>
                                <p class="text-sm">پاسخ کارشناس نمونه</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="color" name="chat_staff_bg" x-model="color"
                                   value="{{ $settings['chat_staff_bg'] }}"
                                   class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                            <div class="flex-1">
                                <label class="text-xs text-gray-500 mb-1 block">رنگ پس‌زمینه</label>
                                <input type="text" x-model="color" class="form-input font-mono text-xs" placeholder="#dcf8c6" dir="ltr">
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mt-3">
                            <input type="color" name="chat_staff_text" value="{{ $settings['chat_staff_text'] }}"
                                   class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                            <div class="flex-1">
                                <label class="text-xs text-gray-500 mb-1 block">رنگ متن</label>
                                <input type="text" value="{{ $settings['chat_staff_text'] }}" class="form-input font-mono text-xs" placeholder="#1a1a2e" dir="ltr"
                                       @input="document.querySelector('[name=chat_staff_text]').value = $el.value">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ TAB: Behavior ══════════════════════════════════════════════ --}}
        <div x-show="tab === 'behavior'" x-transition>
            <div class="glass-card p-6">
                <h3 class="font-bold text-gray-800 mb-5 flex items-center gap-2">
                    <span class="w-8 h-8 bg-gradient-to-r from-blue-400 to-cyan-500 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    </span>
                    رفتار پیش‌فرض ماژول
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-core-ui::input
                        type="number"
                        name="auto_close_days"
                        :value="$settings['auto_close_days']"
                        label="بستن خودکار تیکت پس از (روز)"
                        placeholder="7"
                        hint="۰ = غیرفعال"
                        min="0" max="365" />

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">اولویت پیش‌فرض تیکت‌های جدید</label>
                        <select name="default_priority" class="form-input">
                            <option value="low"    {{ $settings['default_priority'] === 'low'    ? 'selected' : '' }}>کم</option>
                            <option value="normal" {{ $settings['default_priority'] === 'normal' ? 'selected' : '' }}>عادی</option>
                            <option value="high"   {{ $settings['default_priority'] === 'high'   ? 'selected' : '' }}>بالا</option>
                        </select>
                    </div>

                    <x-core-ui::input
                        type="number"
                        name="max_message_length"
                        :value="$settings['max_message_length']"
                        label="حداکثر طول پیام (کاراکتر)"
                        placeholder="5000"
                        min="100" max="10000" />
                </div>
            </div>
        </div>

        {{-- ═══ TAB: SMS Config ═══════════════════════════════════════════ --}}
        <div x-show="tab === 'sms'" x-transition>
            <div class="glass-card p-6">
                <h3 class="font-bold text-gray-800 mb-5 flex items-center gap-2">
                    <span class="w-8 h-8 bg-gradient-to-r from-violet-400 to-purple-500 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </span>
                    تنظیمات پیامک
                </h3>

                <div class="space-y-4">

                    {{-- SMS master toggle --}}
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">فعال‌سازی پیامک</p>
                            <p class="text-xs text-gray-500 mt-0.5">ارسال اعلان‌های خودکار پیامکی</p>
                        </div>
                        <label class="toggle-pill">
                            <input type="checkbox" name="sms_enabled" value="1" {{ $settings['sms_enabled'] ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </div>

                    {{-- Admin phone --}}
                    <x-core-ui::input
                        name="sms_admin_phone"
                        :value="$settings['sms_admin_phone']"
                        label="شماره ادمین برای دریافت اعلان"
                        placeholder="09xxxxxxxxx"
                        maxlength="20"
                        dir="ltr" />

                    {{-- Event toggles --}}
                    <p class="text-sm font-semibold text-gray-700 mt-2">رویدادهای فعال پیامک:</p>

                    @php
                    $events = [
                        'sms_on_created'  => ['ثبت تیکت جدید',       'پیامک به ادمین هنگام ثبت تیکت جدید'],
                        'sms_on_reply'    => ['پاسخ مشتری',          'پیامک به ادمین هنگام پاسخ مشتری'],
                        'sms_on_status'   => ['تغییر وضعیت',         'پیامک هنگام تغییر وضعیت تیکت'],
                        'sms_on_assigned' => ['تخصیص به کارشناس',    'پیامک به کارشناس هنگام تخصیص'],
                    ];
                    @endphp

                    <div class="space-y-3">
                        @foreach($events as $key => [$label, $desc])
                        <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-200">
                            <div>
                                <p class="text-sm font-medium text-gray-700">{{ $label }}</p>
                                <p class="text-xs text-gray-400">{{ $desc }}</p>
                            </div>
                            <label class="toggle-pill">
                                <input type="checkbox" name="{{ $key }}" value="1" {{ $settings[$key] ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ TAB: SMS Templates ════════════════════════════════════════ --}}
        <div x-show="tab === 'templates'" x-transition>
            <div class="glass-card p-6">
                <h3 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
                    <span class="w-8 h-8 bg-gradient-to-r from-orange-400 to-pink-500 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    </span>
                    قالب‌های پیامک خودکار
                </h3>
                <p class="text-xs text-gray-500 mb-5">از متغیرهای <code class="bg-gray-100 px-1.5 py-0.5 rounded">{`{ticket_number}`}</code>، <code class="bg-gray-100 px-1.5 py-0.5 rounded">{`{subject}`}</code>، <code class="bg-gray-100 px-1.5 py-0.5 rounded">{`{customer}`}</code>، <code class="bg-gray-100 px-1.5 py-0.5 rounded">{`{from_status}`}</code>، <code class="bg-gray-100 px-1.5 py-0.5 rounded">{`{to_status}`}</code> می‌توانید استفاده کنید.</p>

                @php
                $templates = [
                    'sms_template_created'        => ['ثبت تیکت جدید',     '{ticket_number}، {subject}، {customer}'],
                    'sms_template_customer_reply' => ['پاسخ مشتری',        '{ticket_number}، {customer}'],
                    'sms_template_status'         => ['تغییر وضعیت',       '{ticket_number}، {from_status}، {to_status}'],
                    'sms_template_assigned'       => ['تخصیص به کارشناس', '{ticket_number}، {subject}'],
                ];
                @endphp

                <div class="space-y-5">
                    @foreach($templates as $key => [$label, $vars])
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ $label }}</label>
                        <p class="text-[11px] text-indigo-500 mb-1.5">متغیرها: {{ $vars }}</p>
                        <textarea name="{{ $key }}" rows="3" maxlength="500"
                                  class="form-input resize-none"
                                  placeholder="متن قالب پیامک را بنویسید...">{{ $settings[$key] }}</textarea>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Save button (always visible) --}}
        <div class="mt-5 flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-7 py-3 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-md hover:shadow-lg transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                ذخیره تنظیمات
            </button>
        </div>

    </form>
</div>
@endsection
