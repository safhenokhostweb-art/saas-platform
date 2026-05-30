
@extends('layouts.admin')

@section('header', 'کاربر جدید')

@section('content')
<div x-data=\"createUserForm()\" class=\"max-w-4xl mx-auto\">
    {{-- Toast Notification --}}
    @include('admin.users._toast')

    <div class=\"card\">
        <div class=\"card-body\">
            <form method=\"POST\" action=\"{{ route('admin.users.store') }}\" @submit.prevent=\"submitForm\">
                @csrf

                {{-- Hidden error container for server-side errors --}}
                <div x-show=\"serverErrors.length > 0\" x-transition
                     class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4\"
                     style=\"display: none;\">
                    <ul>
                        <template x-for=\"(err, index) in serverErrors\" :key=\"index\">
                            <li x-text=\"err\"></li>
                        </template>
                    </ul>
                </div>

                <div class=\"grid grid-cols-1 md:grid-cols-2 gap-4\">
                    {{-- Mobile --}}
                    <div class=\"mb-3 text-end\">
                        <label class=\"form-label\">موبایل *</label>
                        <input type=\"text\" x-model=\"form.mobile\" name=\"mobile\"
                               class=\"form-control\"
                               :class=\"{'is-invalid': errors.mobile}\"
                               placeholder=\"۰۹۱۲۳۴۵۶۷۸۹\" required>
                        <div x-show=\"errors.mobile\" class=\"text-red-500 text-xs mt-1\" x-text=\"errors.mobile\"></div>
                    </div>


                    {{-- Password --}}
                    <div class=\"mb-3 text-end\">
                        <label class=\"form-label\">رمز عبور *</label>
                        <input type=\"password\" x-model=\"form.password\" name=\"password\"
                               class=\"form-control\"
                               :class=\"{'is-invalid': errors.password}\"
                               placeholder=\"حداقل ۸ کاراکتر\" required>
                        <div x-show=\"errors.password\" class=\"text-red-500 text-xs mt-1\" x-text=\"errors.password\"></div>
                        <div class=\"flex items-center gap-2 mt-1\">
                            <span class=\"text-xs text-gray-500\">قدرت رمز:</span>
                            <div class=\"h-2 w-24 bg-gray-200 rounded-full overflow-hidden\">
                                <div :style=\"'width: ' + passwordStrength + '%'\"
                                     :class=\"passwordStrength < 40 ? 'bg-red-400' : passwordStrength < 70 ? 'bg-yellow-400' : 'bg-green-400'\"
                                     class=\"h-full rounded-full transition-all duration-300\"></div>
                            </div>
                        </div>
                    </div>

                    {{-- First Name --}}
                    <div class=\"mb-3 text-end\">
                        <label class=\"form-label\">نام</label>
                        <input type=\"text\" x-model=\"form.first_name\" name=\"first_name\"
                               class=\"form-control\" placeholder=\"نام\">
                    </div>

                    {{-- Last Name --}}
                    <div class=\"mb-3 text-end\">
                        <label class=\"form-label\">نام خانوادگی</label>
                        <input type=\"text\" x-model=\"form.last_name\" name=\"last_name\"
                               class=\"form-control\" placeholder=\"نام خانوادگی\">
                    </div>

                    {{-- Roles Multi-Select --}}
                    <div class=\"mb-3 text-end\" x-data=\"{ open: false, search: '' }\">
                        <label class=\"form-label\">نقش‌ها</label>
                        <div class=\"relative\">
                            <button @click=\"open = !open\" type=\"button\"
                                    class=\"form-select text-right w-full flex items-center justify-between\">
                                <span x-text=\"selectedRoles.length ? selectedRoles.join(', ') : 'انتخاب نقش‌ها...'\"
                                      class=\"text-sm\"></span>
                                <svg class=\"w-4 h-4 transition-transform\" :class=\"{'rotate-180': open}\"
                                     fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                                    <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 9l-7 7-7-7\"/>
                                </svg>
                            </button>
                            <div x-show=\"open\" @click.outside=\"open = false\"
                                 class=\"absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto\"
                                 style=\"display: none;\">
                                <div class=\"p-2 border-b border-gray-100\">
                                    <input type=\"text\" x-model=\"search\" placeholder=\"جستجوی نقش...\"
                                           class=\"form-control form-control-sm text-sm\">
                                </div>
                                <template x-for=\"role in availableRoles\" :key=\"role.id\">
                                    <label class=\"flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer\"
                                           x-show=\"!search || role.name.includes(search)\">
                                        <input type=\"checkbox\" :value=\"role.id\" x-model=\"selectedRolesIds\"
                                               class=\"form-check-input\">
                                        <span class=\"text-sm\" x-text=\"role.name\"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <template x-for=\"roleId in selectedRolesIds\" :key=\"roleId\">
                            <input type=\"hidden\" name=\"roles[]\" :value=\"roleId\">
                        </template>
                    </div>
                </div>

                {{-- Toggles --}}
                <div class=\"flex items-center gap-4 mt-4\">
                    <label class=\"flex items-center gap-2 cursor-pointer\">
                        <input type=\"checkbox\" x-model=\"form.is_active\" name=\"is_active\" value=\"1\"
                               class=\"form-check-input\" checked>
                        <span>فعال</span>
                    </label>
                    <label class=\"flex items-center gap-2 cursor-pointer\">
                        <input type=\"checkbox\" x-model=\"form.is_admin\" name=\"is_admin\" value=\"1\"
                               class=\"form-check-input\">
                        <span>ادمین</span>
                    </label>
                    <label class=\"flex items-center gap-2 cursor-pointer\">
                        <input type=\"checkbox\" x-model=\"form.send_welcome_sms\" name=\"send_welcome_sms\" value=\"1\"
                               class=\"form-check-input\" checked>
                        <span>ارسال پیام خوش‌آمدگویی</span>
                    </label>
                </div>

                {{-- Buttons --}}
                <div class=\"mt-6 flex items-center gap-3\">
                    <button type=\"submit\" class=\"btn btn-primary\" :disabled=\"isSubmitting\">
                        <svg x-show=\"isSubmitting\" class=\"inline w-4 h-4 animate-spin -ml-1 mr-2\"
                             fill=\"none\" viewBox=\"0 0 24 24\">
                            <circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"/>
                            <path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z\"/>
                        </svg>
                        <span x-text=\"isSubmitting ? 'در حال ایجاد...' : 'ایجاد کاربر'\"></span>
                    </button>
                    <a href=\"{{ route('admin.users.index') }}\" class=\"btn btn-secondary\">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function createUserForm() {
        return {
            form: {
                mobile: '{{ old('mobile') }}',
                password: '',
                first_name: '{{ old('first_name') }}',
                last_name: '{{ old('last_name') }}',
                is_active: true,
                is_admin: false,
                send_welcome_sms: true,
            },
            errors: {},
            serverErrors: [],
            isSubmitting: false,
            availableRoles: @json($roles ?? []),
            selectedRolesIds: [],
            passwordStrength: 0,

            get selectedRoles() {
                const roles = [];
                this.selectedRolesIds.forEach(id => {
                    const role = this.availableRoles.find(r => r.id == id);
                    if (role) roles.push(role.name);
                });
                return roles;
            },

            init() {
                this.\$watch('form.password', value => {
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

                if (!this.form.password || this.form.password.length < 8) {
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
                    const formData = new FormData(this.\$el);
                    const response = await fetch('{{ route('admin.users.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
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

                    // Success
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: result.message || 'کاربر با موفقیت ایجاد شد', type: 'success' }
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
@endsection"
