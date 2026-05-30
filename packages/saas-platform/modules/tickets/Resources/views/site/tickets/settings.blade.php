@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', 'تنظیمات چت پشتیبانی')

@section('content')
<x-core-ui::design-tokens />

<div class="{{ config('tickets.site_container', '') }} px-4 py-6 max-w-2xl mx-auto"
     x-data="chatSettingsPage()"
     x-init="load()">

    {{-- Header --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('tickets.site.index') }}"
           class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium text-gray-600 bg-white/70 hover:bg-white border border-gray-200 transition-all hover:shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            بازگشت
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-900">تنظیمات چت پشتیبانی</h2>
            <p class="text-sm text-gray-500 mt-0.5">تنظیمات نمایش و اعلان‌های چت — ذخیره در مرورگر</p>
        </div>
    </div>

    {{-- Saved flash --}}
    <div x-show="saved" x-transition.duration.300ms
         class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5">
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium">تنظیمات با موفقیت ذخیره شد.</span>
    </div>

    {{-- Settings Card --}}
    <div class="glass-card-strong overflow-hidden">

        {{-- Section: اعلان‌ها --}}
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-indigo-50/40 to-purple-50/30">
            <div class="flex items-center gap-2">
                <span class="text-lg">🔔</span>
                <h3 class="text-sm font-bold text-gray-800">اعلان‌ها</h3>
            </div>
        </div>

        <div class="divide-y divide-gray-50">

            {{-- Sound --}}
            <div class="flex items-center justify-between px-6 py-5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-lg shrink-0">🔊</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">صدای اعلان</p>
                        <p class="text-xs text-gray-400 mt-0.5">پخش صدا هنگام دریافت پیام جدید در چت</p>
                    </div>
                </div>
                <button type="button" @click="settings.sound = !settings.sound"
                        :class="settings.sound ? 'bg-indigo-600' : 'bg-gray-200'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none">
                    <span :class="settings.sound ? 'translate-x-5' : 'translate-x-0'"
                          class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200"></span>
                </button>
            </div>

            {{-- Browser Notifications --}}
            <div class="flex items-center justify-between px-6 py-5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center text-lg shrink-0">🖥️</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">اعلان مرورگر</p>
                        <p class="text-xs text-gray-400 mt-0.5">نمایش پوش‌نوتیف سیستم‌عامل هنگام دریافت پیام</p>
                        <p class="text-[11px] text-amber-500 mt-1" x-show="settings.browserNotif && notifBlocked">
                            ⚠️ دسترسی اعلان مسدود است — در تنظیمات مرورگر فعال کنید
                        </p>
                    </div>
                </div>
                <button type="button" @click="toggleBrowserNotif()"
                        :class="settings.browserNotif ? 'bg-purple-600' : 'bg-gray-200'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none">
                    <span :class="settings.browserNotif ? 'translate-x-5' : 'translate-x-0'"
                          class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200"></span>
                </button>
            </div>

        </div>

        {{-- Section: به‌روزرسانی --}}
        <div class="px-6 py-4 border-b border-t border-gray-100 bg-gradient-to-r from-teal-50/40 to-cyan-50/30">
            <div class="flex items-center gap-2">
                <span class="text-lg">🔄</span>
                <h3 class="text-sm font-bold text-gray-800">به‌روزرسانی خودکار</h3>
            </div>
        </div>

        <div class="divide-y divide-gray-50">

            {{-- Poll Interval --}}
            <div class="flex items-center justify-between px-6 py-5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-teal-50 flex items-center justify-center text-lg shrink-0">⏱️</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">بازه بررسی پیام جدید</p>
                        <p class="text-xs text-gray-400 mt-0.5">هر چند ثانیه یک‌بار سرور بررسی شود</p>
                    </div>
                </div>
                <select x-model="settings.pollInterval" style="min-width:7rem"
                        class="px-3 py-2 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-teal-200 focus:border-teal-400 transition-all">
                    <option value="4">۴ ثانیه</option>
                    <option value="8">۸ ثانیه</option>
                    <option value="15">۱۵ ثانیه</option>
                    <option value="30">۳۰ ثانیه</option>
                </select>
            </div>

        </div>

        {{-- Section: نمایش --}}
        <div class="px-6 py-4 border-b border-t border-gray-100 bg-gradient-to-r from-amber-50/40 to-orange-50/30">
            <div class="flex items-center gap-2">
                <span class="text-lg">🎨</span>
                <h3 class="text-sm font-bold text-gray-800">نمایش چت</h3>
            </div>
        </div>

        <div class="divide-y divide-gray-50">

            {{-- Show timestamps --}}
            <div class="flex items-center justify-between px-6 py-5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center text-lg shrink-0">🕐</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">نمایش زمان پیام‌ها</p>
                        <p class="text-xs text-gray-400 mt-0.5">نمایش تاریخ و ساعت زیر هر پیام</p>
                    </div>
                </div>
                <button type="button" @click="settings.showTimestamps = !settings.showTimestamps"
                        :class="settings.showTimestamps ? 'bg-amber-500' : 'bg-gray-200'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none">
                    <span :class="settings.showTimestamps ? 'translate-x-5' : 'translate-x-0'"
                          class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200"></span>
                </button>
            </div>

            {{-- Compact mode --}}
            <div class="flex items-center justify-between px-6 py-5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-sky-50 flex items-center justify-center text-lg shrink-0">📐</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">حالت فشرده</p>
                        <p class="text-xs text-gray-400 mt-0.5">کاهش فاصله بین پیام‌ها برای نمایش بیشتر</p>
                    </div>
                </div>
                <button type="button" @click="settings.compact = !settings.compact"
                        :class="settings.compact ? 'bg-sky-500' : 'bg-gray-200'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none">
                    <span :class="settings.compact ? 'translate-x-5' : 'translate-x-0'"
                          class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200"></span>
                </button>
            </div>

        </div>

        {{-- Footer Actions --}}
        <div class="flex items-center justify-between gap-4 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button type="button" @click="resetDefaults()"
                    class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                بازگشت به پیش‌فرض
            </button>
            <button type="button" @click="save()" class="btn-gradient">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2h2m3-4H9a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-1m-1 4l-3 3m0 0l-3-3m3 3V3"/></svg>
                ذخیره تنظیمات
            </button>
        </div>

    </div>

    {{-- Info note --}}
    <p class="text-xs text-gray-400 text-center mt-4">
        🗂️ تنظیمات فقط در این مرورگر ذخیره می‌شود و برای هر دستگاه جداگانه است.
    </p>

</div>

<script>
function chatSettingsPage() {
    const KEY      = 'tkt_chat_settings';
    const DEFAULTS = {
        sound:          true,
        browserNotif:   false,
        pollInterval:   '4',
        showTimestamps: true,
        compact:        false,
    };

    return {
        settings:     { ...DEFAULTS },
        saved:        false,
        notifBlocked: false,

        load() {
            try {
                const stored = JSON.parse(localStorage.getItem(KEY) || '{}');
                this.settings = { ...DEFAULTS, ...stored };
            } catch(e) {}
            this.notifBlocked = 'Notification' in window && Notification.permission === 'denied';
        },

        async toggleBrowserNotif() {
            if (!this.settings.browserNotif) {
                if ('Notification' in window && Notification.permission === 'default') {
                    const perm = await Notification.requestPermission();
                    if (perm !== 'granted') { this.notifBlocked = true; return; }
                }
                this.notifBlocked = 'Notification' in window && Notification.permission === 'denied';
            }
            this.settings.browserNotif = !this.settings.browserNotif;
        },

        save() {
            localStorage.setItem(KEY, JSON.stringify(this.settings));
            this.saved = true;
            setTimeout(() => this.saved = false, 3000);
        },

        resetDefaults() {
            this.settings = { ...DEFAULTS };
            localStorage.removeItem(KEY);
            this.saved = true;
            setTimeout(() => this.saved = false, 3000);
        },
    };
}
</script>

@endsection
