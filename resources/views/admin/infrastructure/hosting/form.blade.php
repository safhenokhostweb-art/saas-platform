@extends('layouts.admin')

@section('header', $hosting->exists ? 'ویرایش هاستینگ' : 'افزودن هاستینگ')

@section('content')

<div class="max-w-3xl">

    <div class="flex items-center gap-2 mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.infrastructure.overview') }}" class="hover:text-indigo-600">زیرساخت</a>
        <span>/</span>
        <a href="{{ route('admin.infrastructure.hosting.index') }}" class="hover:text-indigo-600">هاستینگ</a>
        <span>/</span>
        <span class="text-gray-800">{{ $hosting->exists ? $hosting->name : 'جدید' }}</span>
    </div>

    <form method="POST" action="{{ $hosting->exists ? route('admin.infrastructure.hosting.update', $hosting) : route('admin.infrastructure.hosting.store') }}">
        @csrf
        @if($hosting->exists) @method('PUT') @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">نام <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') border-red-400 @enderror" value="{{ old('name', $hosting->name) }}" placeholder="VPS پروداکشن اصلی">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">پروایدر <span class="text-red-500">*</span></label>
                    <input type="text" name="provider" class="form-control @error('provider') border-red-400 @enderror" value="{{ old('provider', $hosting->provider) }}" placeholder="Hetzner, DigitalOcean...">
                </div>
                <div>
                    <label class="form-label">پلن / نوع</label>
                    <input type="text" name="plan" class="form-control" value="{{ old('plan', $hosting->plan) }}" placeholder="CX31, 4GB RAM Droplet...">
                </div>
                <div>
                    <label class="form-label">سرور مرتبط</label>
                    <select name="server_id" class="form-select">
                        <option value="">— انتخاب سرور —</option>
                        @foreach($servers as $server)
                        <option value="{{ $server->id }}" {{ old('server_id', $hosting->server_id) == $server->id ? 'selected' : '' }}>{{ $server->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">ایمیل حساب</label>
                    <input type="email" name="account_email" class="form-control" value="{{ old('account_email', $hosting->account_email) }}" dir="ltr">
                </div>
                <div>
                    <label class="form-label">آدرس پنل مدیریت</label>
                    <input type="url" name="panel_url" class="form-control" value="{{ old('panel_url', $hosting->panel_url) }}" dir="ltr" placeholder="https://...">
                </div>
                <div>
                    <label class="form-label">محیط <span class="text-red-500">*</span></label>
                    <select name="environment" class="form-select">
                        <option value="production" {{ old('environment', $hosting->environment ?? 'production') === 'production' ? 'selected' : '' }}>پروداکشن</option>
                        <option value="staging" {{ old('environment', $hosting->environment) === 'staging' ? 'selected' : '' }}>استیجینگ</option>
                        <option value="development" {{ old('environment', $hosting->environment) === 'development' ? 'selected' : '' }}>دولاپمنت</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">وضعیت <span class="text-red-500">*</span></label>
                    <select name="status" class="form-select">
                        @foreach(['active' => 'فعال', 'suspended' => 'معلق', 'cancelled' => 'لغو‌شده', 'pending' => 'در انتظار'] as $v => $l)
                        <option value="{{ $v }}" {{ old('status', $hosting->status ?? 'active') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">منابع اختصاصی</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">vCPU</label>
                        <input type="number" name="cpu_cores_allocated" class="form-control" value="{{ old('cpu_cores_allocated', $hosting->cpu_cores_allocated) }}" min="1">
                    </div>
                    <div>
                        <label class="form-label">RAM (GB)</label>
                        <input type="number" name="ram_gb_allocated" class="form-control" value="{{ old('ram_gb_allocated', $hosting->ram_gb_allocated) }}" min="1">
                    </div>
                    <div>
                        <label class="form-label">دیسک (GB)</label>
                        <input type="number" name="disk_gb_allocated" class="form-control" value="{{ old('disk_gb_allocated', $hosting->disk_gb_allocated) }}" min="1">
                    </div>
                    <div>
                        <label class="form-label">پهنای باند (GB)</label>
                        <input type="number" name="bandwidth_gb_allocated" class="form-control" value="{{ old('bandwidth_gb_allocated', $hosting->bandwidth_gb_allocated) }}" min="0">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">صورتحساب</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">هزینه ماهانه</label>
                        <input type="number" name="monthly_cost" class="form-control" value="{{ old('monthly_cost', $hosting->monthly_cost) }}" min="0" step="0.01">
                    </div>
                    <div>
                        <label class="form-label">ارز</label>
                        <input type="text" name="currency" class="form-control" value="{{ old('currency', $hosting->currency ?? 'IRR') }}" maxlength="3">
                    </div>
                    <div>
                        <label class="form-label">تاریخ تجدید بعدی</label>
                        <x-jalali-datepicker
                            name="next_renewal_at"
                            :value="old('next_renewal_at', $hosting->next_renewal_at ? \App\Helpers\Jalali::format($hosting->next_renewal_at, 'Y/m/d') : '')" />
                    </div>
                    <div>
                        <label class="form-label">شروع دوره صورتحساب</label>
                        <x-jalali-datepicker
                            name="billing_cycle_start"
                            :value="old('billing_cycle_start', $hosting->billing_cycle_start ? \App\Helpers\Jalali::format($hosting->billing_cycle_start, 'Y/m/d') : '')" />
                    </div>
                    <div>
                        <label class="form-label">پایان دوره صورتحساب</label>
                        <x-jalali-datepicker
                            name="billing_cycle_end"
                            :value="old('billing_cycle_end', $hosting->billing_cycle_end ? \App\Helpers\Jalali::format($hosting->billing_cycle_end, 'Y/m/d') : '')" />
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">یادداشت</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $hosting->notes) }}</textarea>
                </div>
            </div>

        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">{{ $hosting->exists ? 'ذخیره تغییرات' : 'ثبت هاستینگ' }}</button>
            <a href="{{ route('admin.infrastructure.hosting.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
</div>

@endsection
