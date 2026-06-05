@extends('layouts.admin')
@section('title', 'پروفایل من')
@section('header', 'پروفایل من')

@section('content')
<div x-data="{
    isSaving: false,
    showSuccess: false,
    avatarPreview: null,
    previewAvatar(e) {
        const f = e.target.files[0];
        if (!f) return;
        const r = new FileReader();
        r.onload = ev => { this.avatarPreview = ev.target.result; };
        r.readAsDataURL(f);
    },
    async save(form) {
        this.isSaving = true;
        try {
            const fd = new FormData(form);
            fd.append('_method', 'PUT');
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                if (data.avatar_url) {
                    document.querySelectorAll('.admin-avatar-img').forEach(el => {
                        el.src = data.avatar_url;
                        el.style.display = '';
                    });
                    document.querySelectorAll('.admin-avatar-initials').forEach(el => el.style.display = 'none');
                }
                this.showSuccess = true;
                setTimeout(() => this.showSuccess = false, 3500);
            }
        } finally { this.isSaving = false; }
    }
}" class="max-w-3xl mx-auto space-y-5">

    {{-- Flash success (server-side) --}}
    {{-- Alpine success (async) --}}
    <div x-show="showSuccess" x-transition
         class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-sm font-medium"
         style="display:none">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        پروفایل با موفقیت به‌روزرسانی شد.
    </div>

    @php
        $avatarPath = $user->profile?->avatar;
        $avatarUrl  = $avatarPath ? url($avatarPath) : null;
        $displayName = $user->profile?->full_name ?: $user->mobile;
        $initial    = mb_substr($displayName, 0, 1);
    @endphp

    <form action="{{ route('admin.profile.update') }}" method="POST"
          enctype="multipart/form-data" @submit.prevent="save($el)">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            {{-- ─── Avatar Card ─────────────────────────────── --}}
            <div class="md:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col items-center gap-4">
                    <div class="relative group">
                        <template x-if="avatarPreview">
                            <img :src="avatarPreview" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-lg admin-avatar-img">
                        </template>
                        <template x-if="!avatarPreview">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-lg admin-avatar-img">
                            @else
                                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center ring-4 ring-white shadow-lg admin-avatar-initials">
                                    <span class="text-3xl font-bold text-white">{{ $initial }}</span>
                                </div>
                            @endif
                        </template>
                        <label class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer" title="تغییر تصویر">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                            </svg>
                            <input type="file" name="avatar" accept="image/*" class="hidden" @change="previewAvatar($event)">
                        </label>
                    </div>
                    <div class="text-center">
                        <p class="font-semibold text-gray-800 text-sm">{{ $displayName }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $user->mobile }}</p>
                        <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 text-[10px] font-semibold">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            مدیر سیستم
                        </span>
                    </div>
                    <p class="text-[10px] text-gray-400 text-center leading-relaxed">
                        روی تصویر کلیک کنید تا آن را تغییر دهید<br>
                        JPG، PNG — حداکثر ۲ مگابایت
                    </p>
                </div>
            </div>

            {{-- ─── Info + Password ──────────────────────────── --}}
            <div class="md:col-span-2 flex flex-col gap-4">

                {{-- Basic info --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        اطلاعات پایه
                    </h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5">نام و نام خانوادگی</label>
                            <input type="text" name="full_name"
                                   value="{{ old('full_name', $user->profile?->full_name) }}"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                                   placeholder="نام و نام خانوادگی خود را وارد کنید">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5">شماره موبایل</label>
                            <input type="text" value="{{ $user->mobile }}" disabled dir="ltr"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-100 text-sm bg-gray-50 text-gray-400 cursor-not-allowed">
                            <p class="text-[10px] text-gray-400 mt-1">شماره موبایل قابل ویرایش نیست.</p>
                        </div>
                    </div>
                </div>

                {{-- Password --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        تغییر رمز عبور
                    </h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5">رمز عبور جدید</label>
                            <input type="password" name="password" dir="ltr"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                                   placeholder="خالی = بدون تغییر">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5">تکرار رمز عبور جدید</label>
                            <input type="password" name="password_confirmation" dir="ltr"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                                   placeholder="تکرار رمز عبور جدید">
                        </div>
                    </div>
                </div>

                {{-- 2FA status (read-only informational) --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        امنیت حساب
                    </h2>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-700">احراز هویت دو مرحله‌ای</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                @if($user->hasTwoFactorEnabled())
                                    فعال — حساب شما دارای لایه حفاظتی اضافه است.
                                @else
                                    غیرفعال
                                @endif
                            </p>
                        </div>
                        @if($user->hasTwoFactorEnabled())
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-semibold">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                فعال
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold">غیرفعال</span>
                        @endif
                    </div>
                </div>

                {{-- Save button --}}
                <div class="flex justify-end">
                    <button type="submit" :disabled="isSaving"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold shadow-md hover:shadow-lg transition-all disabled:opacity-60">
                        <svg x-show="isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <svg x-show="!isSaving" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H7a2 2 0 00-2 2v16l7-3 7 3V5a2 2 0 00-2-2z"/>
                        </svg>
                        <span x-text="isSaving ? 'در حال ذخیره...' : 'ذخیره تغییرات'"></span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
