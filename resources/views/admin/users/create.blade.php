"@extends('layouts.admin')

@section('header', 'کاربر جدید')

@section('content')
<div class=\"card\">
    <div class=\"card-body\">
        <form method=\"POST\" action=\"{{ route('admin.users.store') }}\">
            @csrf

            <div class=\"grid grid-cols-1 md:grid-cols-2 gap-4\">
                <div class=\"mb-3 text-end\">
                    <label class=\"form-label\">موبایل *</label>
                    <input type=\"text\" name=\"mobile\" class=\"form-control\" placeholder=\"۰۹۱۲۳۴۵۶۷۸۹\" value=\"{{ old('mobile') }}\" required>
                    @error('mobile') <span class=\"text-red-500 text-xs\">{{ $message }}</span> @enderror
                </div>

                <div class=\"mb-3 text-end\">
                    <label class=\"form-label\">ایمیل</label>
                    <input type=\"email\" name=\"email\" class=\"form-control\" placeholder=\"email@example.com\" value=\"{{ old('email') }}\">
                    @error('email') <span class=\"text-red-500 text-xs\">{{ $message }}</span> @enderror
                </div>

                <div class=\"mb-3 text-end\">
                    <label class=\"form-label\">رمز عبور</label>
                    <input type=\"password\" name=\"password\" class=\"form-control\" placeholder=\"حداقل ۸ کاراکتر\">
                    @error('password') <span class=\"text-red-500 text-xs\">{{ $message }}</span> @enderror
                </div>

                <div class=\"mb-3 text-end\">
                    <label class=\"form-label\">نام</label>
                    <input type=\"text\" name=\"first_name\" class=\"form-control\" placeholder=\"نام\" value=\"{{ old('first_name') }}\">
                </div>

                <div class=\"mb-3 text-end\">
                    <label class=\"form-label\">نام خانوادگی</label>
                    <input type=\"text\" name=\"last_name\" class=\"form-control\" placeholder=\"نام خانوادگی\" value=\"{{ old('last_name') }}\">
                </div>

                <div class=\"mb-3 text-end\">
                    <label class=\"form-label\">نقش‌ها</label>
                    <select name=\"roles[]\" class=\"form-select\" multiple>
                        @foreach($roles as $role)
                            <option value=\"{{ $role->id }}\">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class=\"flex items-center gap-4 mt-4\">
                <label class=\"flex items-center gap-2\">
                    <input type=\"checkbox\" name=\"is_active\" value=\"1\" checked class=\"form-check-input\">
                    <span>فعال</span>
                </label>
                <label class=\"flex items-center gap-2\">
                    <input type=\"checkbox\" name=\"is_admin\" value=\"1\" class=\"form-check-input\">
                    <span>ادمین</span>
                </label>
            </div>

            <div class=\"mt-4\">
                <button type=\"submit\" class=\"btn btn-primary\">ایجاد کاربر</button>
                <a href=\"{{ route('admin.users.index') }}\" class=\"btn btn-secondary\">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection"