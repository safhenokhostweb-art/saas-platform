@extends('layouts.admin')

@section('title', 'ویرایش کاربر #' . $user->id)

@section('content')
<div x-data="editUserForm()" x-init="initForm()">

    {{-- ─── Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">ویرایش کاربر #{{ $user->id }}</h1>
            <p class="text-sm text-gray-400 mt-0.5">{{ $user->mobile }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 bg-white hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            بازگشت به لیست
        </a>
    </div>

    {{-- ─── Error Banner ───────────────────────────────────────────── --}}
    <div x-show="serverErrors.length > 0" x-transition
         class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-5"
         style="display:none">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <ul class="text-sm space-y-0.5">
            <template x-for="(err, i) in serverErrors" :key="i">
                <li x-text="err"></li>
            </template>
        </ul>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user->id) }}"
          @submit.prevent="submitForm" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- ═══ LEFT: Avatar + Toggles ════════════════════════════ --}}
            <div class="flex flex-col gap-4">

                {{-- Avatar Card --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        تصویر پروفایل
                    </h3>
                    <div class="flex flex-col items-center gap-4">
                        <div class="relative">
                            <template x-if="avatarPreview">
                                <img :src="avatarPreview" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                            </template>
                            <template x-if="!avatarPreview && form.avatar_url">
                                <img :src="form.avatar_url" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                            </template>
                            <template x-if="!avatarPreview && !form.avatar_url">
                                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center border-4 border-white shadow-md">
                                    <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                </div>
                            </template>
                        </div>
                        <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-dashed border-indigo-300 text-indigo-600 text-xs font-medium hover:bg-indigo-50 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                            انتخاب تصویر
                            <input type="file" name="avatar" accept="image/*" class="hidden" @change="previewAvatar($event)">
                        </label>
                        <p class="text-[10px] text-gray-400">JPG, PNG یا GIF — حداکثر ۲ مگابایت</p>
                    </div>
                </div>

                {{-- Status Toggles Card --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        وضعیت حساب
                    </h3>
                    <div class="space-y-3">
                        @foreach([
                            ['key' => 'is_active', 'label' => 'فعال', 'desc' => 'کاربر می‌تواند وارد شود', 'color' => 'indigo'],
                            ['key' => 'is_admin', 'label' => 'مدیر سیستم', 'desc' => 'دسترسی به پنل ادمین', 'color' => 'purple'],
                            ['key' => 'two_factor_enabled', 'label' => 'احراز دو مرحله‌ای', 'desc' => 'ورود با کد OTP', 'color' => 'amber'],
                        ] as $toggle)
                        <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 hover:bg-gray-100/70 transition-all">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-700">{{ $toggle['label'] }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $toggle['desc'] }}</p>
                            </div>
                            <div>
                                <input type="hidden" name="{{ $toggle['key'] }}" value="0">
                                <button type="button"
                                        @click="form.{{ $toggle['key'] }} = !form.{{ $toggle['key'] }}"
                                        :class="form.{{ $toggle['key'] }} ? 'bg-{{ $toggle['color'] }}-500' : 'bg-gray-200'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                                    <span :class="form.{{ $toggle['key'] }} ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                </button>
                                <input type="hidden" :name="'{{ $toggle['key'] }}'" :value="form.{{ $toggle['key'] }} ? '1' : '0'">
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>

            {{-- ═══ RIGHT: Main Info + Roles ══════════════════════════ --}}
            <div class="lg:col-span-2 flex flex-col gap-4">

                {{-- Basic Info Card --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-5 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        اطلاعات پایه
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- Mobile --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">شماره موبایل <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.mobile" name="mobile" dir="ltr"
                                   :class="errors.mobile ? 'border-red-300 ring-red-100' : 'border-gray-200 focus:border-indigo-400 focus:ring-indigo-100'"
                                   class="w-full px-3.5 py-2.5 rounded-xl border text-sm bg-white focus:outline-none focus:ring-2 transition-all"
                                   placeholder="09xxxxxxxxx" required>
                            <p x-show="errors.mobile" x-text="errors.mobile" class="text-xs text-red-500 mt-1"></p>
                        </div>

                        {{-- First Name --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">نام</label>
                            <input type="text" x-model="form.first_name" name="first_name"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                                   placeholder="نام">
                        </div>

                        {{-- Last Name --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">نام خانوادگی</label>
                            <input type="text" x-model="form.last_name" name="last_name"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition-all"
                                   placeholder="نام خانوادگی">
                        </div>

                        {{-- Password --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">رمز عبور جدید</label>
                            <div class="relative">
                                <input type="password" x-model="form.password" name="password" dir="ltr"
                                       :class="errors.password ? 'border-red-300 ring-red-100' : 'border-gray-200 focus:border-indigo-400 focus:ring-indigo-100'"
                                       class="w-full px-3.5 py-2.5 rounded-xl border text-sm bg-white focus:outline-none focus:ring-2 transition-all"
                                       placeholder="خالی = بدون تغییر">
                            </div>
                            <p x-show="errors.password" x-text="errors.password" class="text-xs text-red-500 mt-1"></p>
                            {{-- Password strength --}}
                            <div x-show="form.password.length > 0" class="mt-2">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-gray-400">قدرت رمز</span>
                                    <span class="text-[10px] font-semibold"
                                          :class="passwordStrength < 40 ? 'text-red-500' : passwordStrength < 70 ? 'text-amber-500' : 'text-green-500'"
                                          x-text="passwordStrength < 40 ? 'ضعیف' : passwordStrength < 70 ? 'متوسط' : 'قوی'"></span>
                                </div>
                                <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div :style="'width: ' + passwordStrength + '%'"
                                         :class="passwordStrength < 40 ? 'bg-red-400' : passwordStrength < 70 ? 'bg-amber-400' : 'bg-green-400'"
                                         class="h-full rounded-full transition-all duration-300"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Roles Card --}}
                @if(isset($roles) && $roles->count())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.992l1.003.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        نقش‌ها و دسترسی‌ها
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($roles as $role)
                        <label class="relative flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="selectedRolesIds.includes('{{ $role->id }}') ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300'">
                            <input type="checkbox" class="hidden"
                                   :checked="selectedRolesIds.includes('{{ $role->id }}')"
                                   @change="toggleRole('{{ $role->id }}')">
                            <div class="w-4 h-4 rounded flex items-center justify-center border transition-all shrink-0"
                                 :class="selectedRolesIds.includes('{{ $role->id }}') ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white'">
                                <svg x-show="selectedRolesIds.includes('{{ $role->id }}')" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </div>
                            <span class="text-xs font-medium"
                                  :class="selectedRolesIds.includes('{{ $role->id }}') ? 'text-indigo-700' : 'text-gray-600'">
                                {{ $role->name }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                    <template x-for="roleId in selectedRolesIds" :key="roleId">
                        <input type="hidden" name="roles[]" :value="roleId">
                    </template>
                </div>
                @endif

                {{-- Action Buttons --}}
                <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
                    <a href="{{ route('admin.users.index') }}"
                       class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-all">
                        انصراف
                    </a>
                    <button type="submit" :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold shadow-md hover:shadow-lg transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg x-show="!isSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 3H7a2 2 0 00-2 2v16l7-3 7 3V5a2 2 0 00-2-2z"/></svg>
                        <span x-text="isSubmitting ? 'در حال ذخیره...' : 'ذخیره تغییرات'"></span>
                    </button>
                </div>

            </div>
        </div>
    </form>

</div>

@push('scripts')
<script>
function editUserForm() {
    return {
        form: {
            mobile: '',
            first_name: '',
            last_name: '',
            password: '',
            avatar_url: '',
            is_active: true,
            is_admin: false,
            two_factor_enabled: false,
        },
        errors: {},
        serverErrors: [],
        isSubmitting: false,
        selectedRolesIds: [],
        avatarPreview: null,
        passwordStrength: 0,

        initForm() {
            @php
                $fullName  = $user->profile?->full_name ?? '';
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName  = $nameParts[1] ?? '';
                $avatarUrl = $user->profile?->avatar_url ?? '';
            @endphp
            this.form.mobile             = '{{ addslashes($user->mobile) }}';
            this.form.first_name         = '{{ addslashes($firstName) }}';
            this.form.last_name          = '{{ addslashes($lastName) }}';
            this.form.avatar_url         = '{{ addslashes($avatarUrl) }}';
            this.form.is_active          = {{ $user->is_active ? 'true' : 'false' }};
            this.form.is_admin           = {{ $user->is_admin ? 'true' : 'false' }};
            this.form.two_factor_enabled = {{ $user->hasTwoFactorEnabled() ? 'true' : 'false' }};
            this.selectedRolesIds        = @json($user->roles->pluck('id')->map(fn($id) => (string)$id)->toArray());

            this.$watch('form.password', value => this.calculatePasswordStrength(value));
        },

        calculatePasswordStrength(password) {
            if (!password) { this.passwordStrength = 0; return; }
            let score = 0;
            if (password.length >= 8)  score += 25;
            if (password.length >= 12) score += 15;
            if (/[a-z]/.test(password)) score += 15;
            if (/[A-Z]/.test(password)) score += 15;
            if (/[0-9]/.test(password)) score += 15;
            if (/[^a-zA-Z0-9]/.test(password)) score += 15;
            this.passwordStrength = Math.min(100, score);
        },

        toggleRole(roleId) {
            const index = this.selectedRolesIds.indexOf(roleId);
            if (index === -1) this.selectedRolesIds.push(roleId);
            else this.selectedRolesIds.splice(index, 1);
        },

        previewAvatar(event) {
            const file = event.target.files[0];
            if (!file) { this.avatarPreview = null; return; }
            const reader = new FileReader();
            reader.onload = e => { this.avatarPreview = e.target.result; };
            reader.readAsDataURL(file);
        },

        validate() {
            this.errors = {};
            let valid = true;
            if (!this.form.mobile) {
                this.errors.mobile = 'شماره موبایل الزامی است';
                valid = false;
            } else if (!/^09[0-9]{9}$/.test(this.form.mobile)) {
                this.errors.mobile = 'فرمت معتبر نیست (مثال: 09123456789)';
                valid = false;
            }
            if (this.form.password && this.form.password.length > 0 && this.form.password.length < 8) {
                this.errors.password = 'رمز عبور باید حداقل ۸ کاراکتر باشد';
                valid = false;
            }
            return valid;
        },

        async submitForm() {
            if (!this.validate()) return;
            this.isSubmitting = true;
            this.serverErrors = [];
            this.errors = {};
            try {
                const formData = new FormData(this.$el.querySelector('form') || this.$el);
                this.selectedRolesIds.forEach(id => formData.append('roles[]', id));

                const response = await fetch('{{ route('admin.users.update', $user->id) }}', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-HTTP-Method-Override': 'PUT',
                    },
                    body: formData
                });
                const result = await response.json();

                if (!response.ok) {
                    if (result.errors) {
                        Object.values(result.errors).forEach(e => this.serverErrors.push(e[0]));
                    } else if (result.message) {
                        this.serverErrors.push(result.message);
                    }
                    this.isSubmitting = false;
                    return;
                }

                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: result.message || 'تغییرات با موفقیت ذخیره شد', type: 'success' }
                }));
                if (result.avatar_error) {
                    this.serverErrors.push(result.avatar_error);
                    this.isSubmitting = false;
                    return;
                }
                setTimeout(() => {
                    window.location.href = result.redirect || '{{ route('admin.users.index') }}';
                }, 800);
            } catch (e) {
                this.serverErrors.push('خطای شبکه رخ داد. لطفاً دوباره تلاش کنید.');
                this.isSubmitting = false;
            }
        }
    };
}
</script>
@endpush
@endsection
