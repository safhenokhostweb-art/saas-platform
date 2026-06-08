@extends('layouts.admin')

@section('header', $domain->exists ? 'ویرایش دامنه' : 'افزودن دامنه')

@section('content')

<div class="max-w-3xl">

    <div class="flex items-center gap-2 mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.infrastructure.overview') }}" class="hover:text-indigo-600">زیرساخت</a>
        <span>/</span>
        <a href="{{ route('admin.infrastructure.domains.index') }}" class="hover:text-indigo-600">دامنه‌ها</a>
        <span>/</span>
        <span class="text-gray-800">{{ $domain->exists ? $domain->domain : 'جدید' }}</span>
    </div>

    <form method="POST" action="{{ $domain->exists ? route('admin.infrastructure.domains.update', $domain) : route('admin.infrastructure.domains.store') }}">
        @csrf
        @if($domain->exists) @method('PUT') @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-6">

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">اطلاعات دامنه</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">دامنه <span class="text-red-500">*</span></label>
                        <input type="text" name="domain" class="form-control @error('domain') border-red-400 @enderror" value="{{ old('domain', $domain->domain) }}" placeholder="example.com" dir="ltr">
                        @error('domain')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">نوع <span class="text-red-500">*</span></label>
                        <select name="type" class="form-select">
                            @foreach(['tenant' => 'تِننت (مشتری)', 'platform' => 'پلتفرم (ادمین)', 'cdn' => 'CDN', 'api' => 'API', 'mail' => 'ایمیل'] as $val => $label)
                            <option value="{{ $val }}" {{ old('type', $domain->type ?? 'tenant') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">سایت تِننت مرتبط</label>
                        <select name="site_id" class="form-select">
                            <option value="">— بدون تِننت —</option>
                            @foreach($sites as $site)
                            <option value="{{ $site->id }}" {{ old('site_id', $domain->site_id) == $site->id ? 'selected' : '' }}>{{ $site->title }} ({{ $site->domain ?? $site->slug }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">سرور</label>
                        <select name="server_id" class="form-select">
                            <option value="">— انتخاب سرور —</option>
                            @foreach($servers as $server)
                            <option value="{{ $server->id }}" {{ old('server_id', $domain->server_id) == $server->id ? 'selected' : '' }}>{{ $server->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">ثبت‌کننده</label>
                        <input type="text" name="registrar" class="form-control" value="{{ old('registrar', $domain->registrar) }}" placeholder="NIC.ir, Namecheap...">
                    </div>
                    <div>
                        <label class="form-label">پروایدر DNS</label>
                        <input type="text" name="dns_provider" class="form-control" value="{{ old('dns_provider', $domain->dns_provider) }}" placeholder="Cloudflare, ArvanCloud...">
                    </div>
                    <div>
                        <label class="form-label">تاریخ ثبت</label>
                        <input type="date" name="registered_at" class="form-control" value="{{ old('registered_at', $domain->registered_at?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">تاریخ انقضا</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at', $domain->expires_at?->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">یادداشت DNS</label>
                    <textarea name="dns_notes" class="form-control" rows="2" placeholder="رکوردهای مهم A, CNAME, MX...">{{ old('dns_notes', $domain->dns_notes) }}</textarea>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">گواهی SSL</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" name="ssl_enabled" value="1" id="ssl_enabled" {{ old('ssl_enabled', $domain->ssl_enabled) ? 'checked' : '' }} class="rounded border-gray-300">
                        <label for="ssl_enabled" class="text-sm text-gray-700 cursor-pointer">SSL فعال است</label>
                    </div>
                    <div>
                        <label class="form-label">پروایدر SSL</label>
                        <input type="text" name="ssl_provider" class="form-control" value="{{ old('ssl_provider', $domain->ssl_provider) }}" placeholder="Let's Encrypt, ZeroSSL...">
                    </div>
                    <div>
                        <label class="form-label">انقضای SSL</label>
                        <input type="date" name="ssl_expires_at" class="form-control" value="{{ old('ssl_expires_at', $domain->ssl_expires_at?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">وضعیت SSL <span class="text-red-500">*</span></label>
                        <select name="ssl_status" class="form-select">
                            @foreach(['valid' => 'معتبر', 'expiring_soon' => 'رو به انقضا', 'expired' => 'منقضی', 'unknown' => 'نامشخص'] as $val => $label)
                            <option value="{{ $val }}" {{ old('ssl_status', $domain->ssl_status ?? 'unknown') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" name="redirects_to_https" value="1" id="redirects_to_https" {{ old('redirects_to_https', $domain->redirects_to_https) ? 'checked' : '' }} class="rounded border-gray-300">
                        <label for="redirects_to_https" class="text-sm text-gray-700 cursor-pointer">ریدایرکت HTTP ← HTTPS فعال است</label>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-gray-700 border-b border-gray-100 pb-2 mb-4">وضعیت کلی</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">وضعیت دامنه <span class="text-red-500">*</span></label>
                        <select name="status" class="form-select">
                            @foreach(['active' => 'فعال', 'inactive' => 'غیرفعال', 'parked' => 'پارک‌شده', 'transferred' => 'منتقل‌شده'] as $val => $label)
                            <option value="{{ $val }}" {{ old('status', $domain->status ?? 'active') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">یادداشت</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="توضیحات اضافی...">{{ old('notes', $domain->notes) }}</textarea>
                </div>
            </div>

        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">{{ $domain->exists ? 'ذخیره تغییرات' : 'ثبت دامنه' }}</button>
            <a href="{{ route('admin.infrastructure.domains.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>

    </form>
</div>

@endsection
