@extends('layouts.admin')

@section('header', $server->exists ? 'ویرایش سرور' : 'افزودن سرور')

@section('content')

<div class="max-w-3xl">

    <div class="flex items-center gap-2 mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.infrastructure.overview') }}" class="hover:text-indigo-600">زیرساخت</a>
        <span>/</span>
        <a href="{{ route('admin.infrastructure.servers.index') }}" class="hover:text-indigo-600">سرورها</a>
        <span>/</span>
        <span class="text-gray-800">{{ $server->exists ? $server->name : 'جدید' }}</span>
    </div>

    <form method="POST" action="{{ $server->exists ? route('admin.infrastructure.servers.update', $server) : route('admin.infrastructure.servers.store') }}">
        @csrf
        @if($server->exists) @method('PUT') @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-6">

            {{-- Section: Identity --}}
            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">اطلاعات پایه</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">نام سرور <span class="text-red-500">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') border-red-400 @enderror" value="{{ old('name', $server->name) }}" placeholder="مثلاً: VPS-MAIN-1">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">هاست‌نیم</label>
                        <input type="text" name="hostname" class="form-control" value="{{ old('hostname', $server->hostname) }}" placeholder="server1.example.com">
                    </div>
                    <div>
                        <label class="form-label">آدرس IPv4</label>
                        <input type="text" name="ip_address" class="form-control @error('ip_address') border-red-400 @enderror" value="{{ old('ip_address', $server->ip_address) }}" placeholder="1.2.3.4" dir="ltr">
                        @error('ip_address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">آدرس IPv6</label>
                        <input type="text" name="ipv6_address" class="form-control" value="{{ old('ipv6_address', $server->ipv6_address) }}" placeholder="2001:db8::1" dir="ltr">
                    </div>
                    <div>
                        <label class="form-label">پروایدر (هاستینگ)</label>
                        <input type="text" name="provider" class="form-control" value="{{ old('provider', $server->provider) }}" placeholder="Hetzner, DigitalOcean, ArvanCloud...">
                    </div>
                    <div>
                        <label class="form-label">دیتاسنتر</label>
                        <input type="text" name="datacenter" class="form-control" value="{{ old('datacenter', $server->datacenter) }}" placeholder="FSN1, AMS3...">
                    </div>
                    <div>
                        <label class="form-label">سیستم‌عامل</label>
                        <input type="text" name="os" class="form-control" value="{{ old('os', $server->os) }}" placeholder="Ubuntu 22.04 LTS">
                    </div>
                    <div>
                        <label class="form-label">محیط <span class="text-red-500">*</span></label>
                        <select name="environment" class="form-select">
                            <option value="production" {{ old('environment', $server->environment) === 'production' ? 'selected' : '' }}>پروداکشن</option>
                            <option value="staging" {{ old('environment', $server->environment) === 'staging' ? 'selected' : '' }}>استیجینگ</option>
                            <option value="development" {{ old('environment', $server->environment) === 'development' ? 'selected' : '' }}>دولاپمنت</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Section: Roles --}}
            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">نقش‌ها</h3>
                @php $currentRoles = old('roles', $server->roles ?? []); @endphp
                <div class="flex flex-wrap gap-3">
                    @foreach(['application' => 'اپلیکیشن', 'database' => 'دیتابیس', 'storage' => 'استوریج', 'cache' => 'کش / Redis', 'queue' => 'صف پیام'] as $val => $label)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="roles[]" value="{{ $val }}" {{ in_array($val, $currentRoles) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Section: Resources --}}
            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">منابع</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">هسته CPU</label>
                        <input type="number" name="cpu_cores" class="form-control" value="{{ old('cpu_cores', $server->cpu_cores) }}" min="1" placeholder="4">
                    </div>
                    <div>
                        <label class="form-label">RAM (GB)</label>
                        <input type="number" name="ram_gb" class="form-control" value="{{ old('ram_gb', $server->ram_gb) }}" min="1" placeholder="8">
                    </div>
                    <div>
                        <label class="form-label">دیسک (GB)</label>
                        <input type="number" name="disk_gb" class="form-control" value="{{ old('disk_gb', $server->disk_gb) }}" min="1" placeholder="160">
                    </div>
                    <div>
                        <label class="form-label">هزینه ماهانه</label>
                        <input type="number" name="monthly_cost" class="form-control" value="{{ old('monthly_cost', $server->monthly_cost) }}" min="0" step="0.01" placeholder="0">
                    </div>
                </div>
            </div>

            {{-- Section: Status --}}
            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">وضعیت</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">وضعیت فعلی <span class="text-red-500">*</span></label>
                        <select name="status" class="form-select">
                            @foreach(['online' => 'آنلاین', 'offline' => 'آفلاین', 'degraded' => 'ضعیف', 'maintenance' => 'تعمیرات', 'unknown' => 'نامشخص'] as $val => $label)
                            <option value="{{ $val }}" {{ old('status', $server->status ?? 'unknown') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">آپتایم (%)</label>
                        <input type="number" name="uptime_percent" class="form-control" value="{{ old('uptime_percent', $server->uptime_percent) }}" min="0" max="100" placeholder="99">
                    </div>
                    <div>
                        <label class="form-label">آخرین پشتیبان‌گیری</label>
                        <x-jalali-datepicker
                            name="last_backup_at"
                            :value="old('last_backup_at', $server->last_backup_at ? \App\Helpers\Jalali::format($server->last_backup_at, 'Y/m/d') : '')" />
                    </div>
                    <div>
                        <label class="form-label">آخرین استقرار (دیپلوی)</label>
                        <x-jalali-datepicker
                            name="last_deployment_at"
                            :value="old('last_deployment_at', $server->last_deployment_at ? \App\Helpers\Jalali::format($server->last_deployment_at, 'Y/m/d') : '')" />
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">یادداشت</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="توضیحات اضافی درباره این سرور...">{{ old('notes', $server->notes) }}</textarea>
                </div>
                <div class="mt-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $server->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">سرور فعال است</span>
                    </label>
                </div>
            </div>

        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">{{ $server->exists ? 'ذخیره تغییرات' : 'ثبت سرور' }}</button>
            <a href="{{ route('admin.infrastructure.servers.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>

    </form>
</div>

@endsection
