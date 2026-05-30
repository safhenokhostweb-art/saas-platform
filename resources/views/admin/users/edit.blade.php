"@extends('layouts.admin')

@section('title', 'ویرایش کاربر - ' . $user->mobile)

@section('content')
<div x-data=\"editUserForm()\" x-init=\"initForm()\" class=\"container-fluid\">
    {{-- Toast Notification --}}
    @include('admin.users._toast')

    <div class=\"d-flex justify-content-between align-items-center mb-4\">
        <h4 class=\"mb-0\">ویرایش کاربر #{{ $user->id }}</h4>
        <a href=\"{{ route('admin.users.index') }}\" class=\"btn btn-outline-secondary btn-sm\">بازگشت به لیست</a>
    </div>

    <div class=\"card\">
        <div class=\"card-body\">
            <form method=\"POST\" action=\"{{ route('admin.users.update', $user->id) }}\"
                  @submit.prevent=\"submitForm\" enctype=\"multipart/form-data\">
                @csrf
                @method('PUT')

                {{-- Server Errors --}}
                <div x-show=\"serverErrors.length > 0\" x-transition
                     class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4\"
                     style=\"display: none;\">
                    <ul>
                        <template x-for=\"(err, index) in serverErrors\" :key=\"index\">
                            <li x-text=\"err\"></li>
                        </template>
                    </ul>
                </div>

                <div class=\"row\">
                    {{-- Mobile --}}
                    <div class=\"col-md-6 mb-3\">
                        <label class=\"form-label\">شماره موبایل <span class=\"text-danger\">*</span></label>
                        <input type=\"text\" x-model=\"form.mobile\" name=\"mobile\"
                               class=\"form-control\" :class=\"{'is-invalid': errors.mobile}\" required>
                        <div x-show=\"errors.mobile\" class=\"invalid-feedback d-block\" x-text=\"errors.mobile\"></div>
                    </div>


                    {{-- First Name --}}
                    <div class=\"col-md-6 mb-3\">
                        <label class=\"form-label\">نام</label>
                        <input type=\"text\" x-model=\"form.first_name\" name=\"first_name\" class=\"form-control\">
                    </div>

                    {{-- Last Name --}}
                    <div class=\"col-md-6 mb-3\">
                        <label class=\"form-label\">نام خانوادگی</label>
                        <input type=\"text\" x-model=\"form.last_name\" name=\"last_name\" class=\"form-control\">
                    </div>

                    {{-- Password --}}
                    <div class=\"col-md-6 mb-3\">
                        <label class=\"form-label\">رمز عبور جدید</label>
                        <input type=\"password\" x-model=\"form.password\" name=\"password\"
                               class=\"form-control\" :class=\"{'is-invalid': errors.password}\"
                               placeholder=\"خالی بگذارید تا تغییر نکند\">
                        <div x-show=\"errors.password\" class=\"invalid-feedback d-block\" x-text=\"errors.password\"></div>
                        <div x-show=\"form.password.length > 0\" class=\"flex items-center gap-2 mt-1\">
                            <span class=\"text-xs text-gray-500\">قدرت رمز:</span>
                            <div class=\"h-2 w-24 bg-gray-200 rounded-full overflow-hidden\">
                                <div :style=\"'width: ' + passwordStrength + '%'\"
                                     :class=\"passwordStrength < 40 ? 'bg-red-400' : passwordStrength < 70 ? 'bg-yellow-400' : 'bg-green-400'\"
                                     class=\"h-full rounded-full transition-all duration-300\"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Avatar --}}
                    <div class=\"col-md-6 mb-3\">
                        <label class=\"form-label\">آواتار</label>
                        <div class=\"flex items-center gap-3\">
                            <template x-if=\"avatarPreview\">
                                <img :src=\"avatarPreview\" class=\"w-16 h-16 rounded-full object-cover border-2 border-gray-200\">
                            </template>
                            <template x-if=\"!avatarPreview && form.avatar_url\">
                                <img :src=\"form.avatar_url\" class=\"w-16 h-16 rounded-full object-cover border-2 border-gray-200\">
                            </template>
                            <input type=\"file\" name=\"avatar\" accept=\"image/*\" class=\"form-control\" @change=\"previewAvatar($event)\">
                        </div>
                    </div>
                </div>

                {{-- Toggles --}}
                <div class=\"row mb-3\">
                    <div class=\"col-md-6\">
                        <div class=\"form-check form-switch\">
                            <input type=\"hidden\" name=\"is_active\" value=\"0\">
                            <input class=\"form-check-input\" type=\"checkbox\" x-model=\"form.is_active\" id=\"is_active\" value=\"1\">
                            <label class=\"form-check-label\" for=\"is_active\">فعال</label>
                        </div>
                        <div class=\"form-check form-switch\">
                            <input type=\"hidden\" name=\"is_admin\" value=\"0\">
                            <input class=\"form-check-input\" type=\"checkbox\" x-model=\"form.is_admin\" id=\"is_admin\" value=\"1\">
                            <label class=\"form-check-label\" for=\"is_admin\">مدیر سیستم</label>
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"form-check form-switch\">
                            <input type=\"hidden\" name=\"is_verified\" value=\"0\">
                            <input class=\"form-check-input\" type=\"checkbox\" x-model=\"form.is_verified\" id=\"is_verified\" value=\"1\">
                            <label class=\"form-check-label\" for=\"is_verified\">تأیید شده</label>
                        </div>
                        <div class=\"form-check form-switch\">
                            <input type=\"hidden\" name=\"two_factor_enabled\" value=\"0\">
                            <input class=\"form-check-input\" type=\"checkbox\" x-model=\"form.two_factor_enabled\" id=\"two_factor_enabled\" value=\"1\">
                            <label class=\"form-check-label\" for=\"two_factor_enabled\">احراز هویت دو مرحله‌ای</label>
                        </div>
                    </div>
                </div>

                {{-- Roles --}}
                @if(isset($roles) && $roles->count())
                <div class=\"mb-3\">
                    <label class=\"form-label mb-2\">نقش‌ها</label>
                    <div class=\"row\">
                        @foreach($roles as $role)
                        <div class=\"col-md-3 col-sm-4 col-6 mb-2\">
                            <div class=\"form-check\">
                                <input class=\"form-check-input\" type=\"checkbox\"
                                       :checked=\"selectedRolesIds.includes('{{ $role->id }}')\"
                                       @change=\"toggleRole('{{ $role->id }}')\"
                                       id=\"role_{{ $role->id }}\" value=\"{{ $role->id }}\">
                                <label class=\"form-check-label\" for=\"role_{{ $role->id }}\">{{ $role->name }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <template x-for=\"roleId in selectedRolesIds\" :key=\"roleId\">
                        <input type=\"hidden\" name=\"roles[]\" :value=\"roleId\">
                    </template>
                </div>
                @endif

                {{-- Buttons --}}
                <div class=\"d-flex gap-2\">
                    <button type=\"submit\" class=\"btn btn-primary\" :disabled=\"isSubmitting\">
                        <svg x-show=\"isSubmitting\" class=\"inline w-4 h-4 animate-spin -ml-1 mr-2\"
                             fill=\"none\" viewBox=\"0 0 24 24\">
                            <circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"/>
                            <path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z\"/>
                        </svg>
                        <span x-text=\"isSubmitting ? 'در حال ذخیره...' : 'ذخیره تغییرات'\"></span>
                    </button>
                    <a href=\"{{ route('admin.users.index') }}\" class=\"btn btn-secondary\">انصراف</a>
                </div>
            </form>
        </div>
    </div>
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
                is_verified: false,
                two_factor_enabled: false,
            },
            errors: {},
            serverErrors: [],
            isSubmitting: false,
            selectedRolesIds: [],
            avatarPreview: null,
            passwordStrength: 0,

            initForm() {
                this.form.mobile = '{{ addslashes($user->mobile) }}';
                this.form.first_name = '{{ addslashes($user->profile?->first_name ?? '') }}';
                this.form.last_name = '{{ addslashes($user->profile?->last_name ?? '') }}';
                this.form.avatar_url = '{{ $user->profile?->avatar ?? '' }}';
                this.form.is_active = {{ $user->is_active ? 'true' : 'false' }};
                this.form.is_admin = {{ $user->is_admin ? 'true' : 'false' }};
                this.form.is_verified = {{ $user->is_verified ? 'true' : 'false' }};
                this.form.two_factor_enabled = {{ $user->hasTwoFactorEnabled() ? 'true' : 'false' }};
                this.selectedRolesIds = @json($user->roles->pluck('id')->toArray());

                this.$watch('form.password', value => {
                    this.calculatePasswordStrength(value);
                });
            },

            calculatePasswordStrength(password) {
                let score = 0;
                if (!password) { this.passwordStrength = 0; return; }
                if (password.length >= 8) score += 25;
                if (password.length >= 12) score += 15;
                if (/[a-z]/.test(password)) score += 15;
                if (/[A-Z]/.test(password)) score += 15;
                if (/[0-9]/.test(password)) score += 15;
                if (/[^a-zA-Z0-9]/.test(password)) score += 15;
                this.passwordStrength = Math.min(100, score);
            },

            toggleRole(roleId) {
                const index = this.selectedRolesIds.indexOf(roleId);
                if (index === -1) {
                    this.selectedRolesIds.push(roleId);
                } else {
                    this.selectedRolesIds.splice(index, 1);
                }
            },

            previewAvatar(event) {
                const file = event.target.files[0];
                if (!file) { this.avatarPreview = null; return; }
                const reader = new FileReader();
                reader.onload = (e) => { this.avatarPreview = e.target.result; };
                reader.readAsDataURL(file);
            },

            validate() {
                this.errors = {};
                let valid = true;

                const mobileRegex = /^09[0-9]{9}$/;
                if (!this.form.mobile) {
                    this.errors.mobile = 'موبایل الزامی است';
                    valid = false;
                } else if (!mobileRegex.test(this.form.mobile)) {
                    this.errors.mobile = 'فرمت موبایل معتبر نیست (مثال: ۰۹۱۲۳۴۵۶۷۸۹)';
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
                    const formData = new FormData(this.$el);
                    this.selectedRolesIds.forEach(id => {
                        formData.append('roles[]', id);
                    });

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
                            Object.keys(result.errors).forEach(key => {
                                this.serverErrors.push(result.errors[key][0]);
                            });
                        } else if (result.message) {
                            this.serverErrors.push(result.message);
                        }
                        this.isSubmitting = false;
                        return;
                    }

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: result.message || 'تغییرات با موفقیت ذخیره شد', type: 'success' }
                    }));
                    setTimeout(() => {
                        window.location.href = result.redirect || '{{ route('admin.users.index') }}';
                    }, 800);
                } catch (error) {
                    this.serverErrors.push('خطای شبکه رخ داد. لطفاً دوباره تلاش کنید.');
                    this.isSubmitting = false;
                }
            }
        }
    }
</script>
@endpush
@endsection
