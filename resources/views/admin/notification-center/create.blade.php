@extends('layouts.admin')

@section('header', 'ارسال اعلان جدید')

@push('head')
<style>
.nc-form-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,.05);padding:1.75rem}
.nc-label{display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.4rem}
.nc-input{width:100%;border:1px solid #e2e8f0;border-radius:.625rem;padding:.625rem .875rem;font-size:.875rem;color:#1f2937;background:#f9fafb;transition:all .2s;outline:none}
.nc-input:focus{border-color:#6366f1;background:#fff;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.nc-input.is-invalid{border-color:#ef4444;background:#fff5f5}
.nc-radio-group{display:flex;flex-wrap:wrap;gap:.625rem}
.nc-radio{position:relative;cursor:pointer}
.nc-radio input{position:absolute;opacity:0;width:0;height:0}
.nc-radio-label{display:flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border:2px solid #e2e8f0;border-radius:.625rem;font-size:.82rem;font-weight:600;color:#4b5563;transition:all .15s;cursor:pointer;white-space:nowrap}
.nc-radio input:checked + .nc-radio-label{border-color:#6366f1;background:#f5f3ff;color:#4f46e5}
.nc-radio-label:hover{border-color:#a5b4fc;background:#f5f3ff}

/* Priority radios */
.nc-radio--critical input:checked + .nc-radio-label{border-color:#dc2626;background:#fee2e2;color:#991b1b}
.nc-radio--high    input:checked + .nc-radio-label{border-color:#ea580c;background:#ffedd5;color:#9a3412}
.nc-radio--medium  input:checked + .nc-radio-label{border-color:#d97706;background:#fef3c7;color:#92400e}
.nc-radio--low     input:checked + .nc-radio-label{border-color:#2563eb;background:#eff6ff;color:#1e40af}

/* Preview card */
.nc-preview{border-right:4px solid #6366f1;border-radius:.875rem;background:#f8faff;padding:1rem 1.25rem;transition:all .3s}
.nc-preview--critical{border-right-color:#dc2626;background:#fff5f5}
.nc-preview--high{border-right-color:#ea580c;background:#fff8f5}
.nc-preview--medium{border-right-color:#d97706;background:#fffcf5}
.nc-preview--low{border-right-color:#2563eb;background:#f5f8ff}
</style>
@endpush

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.notification-center.index') }}" class="text-gray-400 hover:text-indigo-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-gray-900">ارسال اعلان جدید</h1>
        <p class="text-gray-500 text-sm mt-0.5">اعلان به مدیران پلتفرم، مدیران سایت یا همه کاربران ارسال کنید</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.notification-center.store') }}"
      x-data="ncForm()" @submit="submitting = true">
@csrf

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:1.5rem;align-items:start">

    {{-- ─── Left: Form ─── --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem">

        {{-- Type --}}
        <div class="nc-form-card">
            <label class="nc-label">نوع اعلان <span class="text-red-500">*</span></label>
            <select name="type" x-model="type" class="nc-input {{ $errors->has('type') ? 'is-invalid' : '' }}">
                @foreach(\App\Models\NotificationCenterLog::TYPES as $val => $label)
                    <option value="{{ $val }}" {{ old('type', 'custom') === $val ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Priority --}}
        <div class="nc-form-card">
            <label class="nc-label">اولویت <span class="text-red-500">*</span></label>
            <div class="nc-radio-group">
                @foreach(['critical'=>'🔴 بحرانی','high'=>'🟠 بالا','medium'=>'🟡 متوسط','low'=>'🔵 پایین'] as $val => $label)
                <label class="nc-radio nc-radio--{{ $val }}">
                    <input type="radio" name="priority" value="{{ $val }}" x-model="priority"
                           {{ old('priority', 'medium') === $val ? 'checked' : '' }}>
                    <span class="nc-radio-label">{{ $label }}</span>
                </label>
                @endforeach
            </div>
            @error('priority')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Title + Message --}}
        <div class="nc-form-card" style="display:flex;flex-direction:column;gap:1rem">
            <div>
                <label class="nc-label">عنوان اعلان <span class="text-red-500">*</span></label>
                <input type="text" name="title" x-model="title"
                       class="nc-input {{ $errors->has('title') ? 'is-invalid' : '' }}"
                       placeholder="عنوان واضح و کوتاه..."
                       value="{{ old('title') }}" maxlength="255">
                @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nc-label">متن پیام</label>
                <textarea name="message" x-model="message" rows="4"
                          class="nc-input {{ $errors->has('message') ? 'is-invalid' : '' }}"
                          placeholder="توضیحات بیشتر (اختیاری)..." maxlength="2000">{{ old('message') }}</textarea>
                @error('message')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="nc-label">لینک اقدام <span class="text-gray-400 font-normal">(اختیاری)</span></label>
                <input type="text" name="link" class="nc-input {{ $errors->has('link') ? 'is-invalid' : '' }}"
                       placeholder="https://... یا /admin/..."
                       value="{{ old('link') }}" dir="ltr">
                @error('link')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Audience --}}
        <div class="nc-form-card">
            <label class="nc-label">مخاطبان <span class="text-red-500">*</span></label>
            <div class="nc-radio-group">
                @foreach(\App\Models\NotificationCenterLog::AUDIENCES as $val => $label)
                <label class="nc-radio">
                    <input type="radio" name="audience_type" value="{{ $val }}" x-model="audience"
                           {{ old('audience_type', 'platform_admin') === $val ? 'checked' : '' }}>
                    <span class="nc-radio-label">{{ $label }}</span>
                </label>
                @endforeach
            </div>
            @error('audience_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

            {{-- Conditional site selector --}}
            <div x-show="audience === 'site_admin'" x-cloak class="mt-3">
                <label class="nc-label">انتخاب سایت <span class="text-red-500">*</span></label>
                <select name="site_id" class="nc-input">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" {{ old('site_id') == $site->id ? 'selected' : '' }}>
                            {{ $site->title }}
                        </option>
                    @endforeach
                </select>
                @error('site_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Warning for broadcast --}}
            <div x-show="audience === 'broadcast' || audience === 'all_site_admins'" x-cloak
                 class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-amber-800 text-xs flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <span>ارسال پیامک به همه کاربران هزینه‌بر است. در صورت انتخاب SMS مطمئن شوید.</span>
            </div>
        </div>

        {{-- Channel --}}
        <div class="nc-form-card">
            <label class="nc-label">کانال ارسال <span class="text-red-500">*</span></label>
            <div class="nc-radio-group">
                @foreach(['in_app'=>'📬 درون‌سیستمی','sms'=>'📱 فقط پیامک','both'=>'📬📱 هر دو','silent'=>'🔕 فقط ثبت'] as $val => $label)
                <label class="nc-radio">
                    <input type="radio" name="channel" value="{{ $val }}"
                           {{ old('channel', 'in_app') === $val ? 'checked' : '' }}>
                    <span class="nc-radio-label">{{ $label }}</span>
                </label>
                @endforeach
            </div>
            @error('channel')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary flex items-center gap-2" :disabled="submitting">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                <span x-text="submitting ? 'در حال ارسال...' : 'ارسال اعلان'"></span>
            </button>
            <a href="{{ route('admin.notification-center.index') }}" class="btn btn-secondary">انصراف</a>
        </div>

    </div>

    {{-- ─── Right: Preview ─── --}}
    <div style="position:sticky;top:1.5rem">
        <div class="nc-form-card">
            <div class="font-bold text-gray-800 text-sm mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                پیش‌نمایش اعلان
            </div>

            <div class="nc-preview" :class="`nc-preview--${priority}`">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                         :style="`background:${priorityBg};color:${priorityColor}`">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-gray-900" x-text="title || 'عنوان اعلان...'"></p>
                        <p class="text-xs text-gray-500 mt-0.5 leading-relaxed" x-text="message || 'متن پیام...'"></p>
                        <div class="flex gap-1.5 mt-2 flex-wrap">
                            <span class="nc-badge" :class="`nc-badge--${priority}`" x-text="priorityFa"></span>
                            <span class="nc-badge" style="background:#f1f5f9;color:#475569" x-text="audienceFa"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 mb-3">راهنمای کانال‌ها:</p>
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex items-start gap-2"><span class="text-indigo-500">📬</span><span><strong>درون‌سیستمی:</strong> فقط در مرکز اطلاع‌رسانی ذخیره می‌شود</span></div>
                    <div class="flex items-start gap-2"><span class="text-green-500">📱</span><span><strong>پیامک:</strong> SMS به شماره موبایل مدیران ارسال می‌شود</span></div>
                    <div class="flex items-start gap-2"><span class="text-amber-500">📬📱</span><span><strong>هر دو:</strong> ذخیره + پیامک همزمان</span></div>
                    <div class="flex items-start gap-2"><span class="text-gray-400">🔕</span><span><strong>فقط ثبت:</strong> بدون ارسال، فقط در تاریخچه ذخیره</span></div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 mb-2">اعلان‌های خودکار سیستم:</p>
                <div class="space-y-1.5 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>
                        خرابی سایت ← بحرانی + پیامک
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
                        بازیابی سایت ← متوسط + درون‌سیستمی
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                        رویداد زیرساختی ← بالا + پیامک
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0"></span>
                        تیکت پشتیبانی ← متوسط + درون‌سیستمی
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

</form>

@push('scripts')
<script>
function ncForm() {
    return {
        type: '{{ old('type', 'custom') }}',
        priority: '{{ old('priority', 'medium') }}',
        title: '{{ old('title', '') }}',
        message: '{{ old('message', '') }}',
        audience: '{{ old('audience_type', 'platform_admin') }}',
        submitting: false,

        get priorityFa() {
            const m = {critical:'بحرانی',high:'بالا',medium:'متوسط',low:'پایین'};
            return m[this.priority] || this.priority;
        },
        get audienceFa() {
            const m = {platform_admin:'مدیران پلتفرم',site_admin:'مدیر سایت خاص',all_site_admins:'همه مدیران',broadcast:'همه کاربران'};
            return m[this.audience] || this.audience;
        },
        get priorityColor() {
            const m = {critical:'#991b1b',high:'#9a3412',medium:'#92400e',low:'#1e40af'};
            return m[this.priority] || '#4f46e5';
        },
        get priorityBg() {
            const m = {critical:'#fee2e2',high:'#ffedd5',medium:'#fef3c7',low:'#eff6ff'};
            return m[this.priority] || '#f5f3ff';
        },
    };
}
</script>
@endpush

@endsection
