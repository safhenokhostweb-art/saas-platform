@extends('layouts.admin')

@section('header', 'تأیید تغییر مخرب: تبدیل ماژول به «داخلی»')

@section('content')
{{--
    Phase 5 / Wave 5.2 / P0-1: صفحهٔ تأیید per-site.
    این صفحه قبل از هرگونه revoke واقعی نمایش داده می‌شود. ادمین می‌تواند برای هر سایت
    «نگه‌دار فعال» را انتخاب/لغو انتخاب کند. سایت‌هایی که خرید موفق یا لایسنس فعال
    دارند به‌طور پیش‌فرض pre-checked هستند تا مطابق global-rules §۴ هیچ entitlement
    خریداری‌شده به‌اشتباه از دست نرود. فقط سایت‌هایی که ادمین به‌صراحت UNCHECK کند، revoke می‌شوند.
--}}

@php
    $commercialSet = array_flip($commerciallyAttachedSiteIds);
@endphp

<div class="mb-4 p-4 rounded-xl border-2 border-red-300 bg-red-50">
    <h3 class="font-bold text-red-800 text-lg mb-2">⚠ هشدار عملیات مخرب</h3>
    <p class="text-sm text-red-700 leading-relaxed">
        شما در حال تغییر نوع ماژول
        <strong>«{{ $module->title ?: $module->slug }}»</strong>
        به نوع <strong>«داخلی»</strong> هستید. این عملیات می‌تواند دسترسی سایت‌های مشتری به این ماژول را غیرفعال کند.
    </p>
    <p class="text-sm text-red-700 mt-2 leading-relaxed">
        بر اساس قانون <em>Immutable Customer Entitlement Protection</em>، هیچ سایتی به‌صورت خودکار revoke نمی‌شود.
        لطفاً برای هر سایت با دقت تصمیم بگیرید. سایت‌هایی که خرید موفق یا لایسنس فعال دارند،
        به‌طور پیش‌فرض <strong>«نگه‌دار فعال»</strong> تیک خورده‌اند.
    </p>
</div>

<form method="POST" action="{{ route('admin.modules.update', $module) }}" class="space-y-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="__confirm_step" value="apply">

    {{-- بازفرستادن همهٔ فیلدهای فرم اولیه (title/description/...) به‌عنوان hidden تا در submit نهایی همه با هم اعمال شوند --}}
    @foreach ($pendingFormData as $k => $v)
        @if (is_array($v))
            @foreach ($v as $vv)
                <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <div class="bg-white border rounded-xl overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b">
            <h4 class="font-semibold">سایت‌های تحت‌تأثیر ({{ count($affectedSiteIds) }})</h4>
            <p class="text-xs text-gray-600 mt-1">
                ✓ نگه‌دار فعال = سایت revoke نمی‌شود. ☐ خالی = سایت revoke خواهد شد (site_modules.is_active=false + ledger.revoke).
            </p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-right">نگه‌دار فعال</th>
                    <th class="px-3 py-2 text-right">شناسهٔ سایت</th>
                    <th class="px-3 py-2 text-right">نام سایت</th>
                    <th class="px-3 py-2 text-right">مشتری</th>
                    <th class="px-3 py-2 text-right">وضعیت تجاری</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sites as $site)
                    @php $isCommercial = isset($commercialSet[$site->id]); @endphp
                    <tr class="border-t">
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox"
                                   name="keep_active_site_ids[]"
                                   value="{{ $site->id }}"
                                   id="keep_{{ $site->id }}"
                                   @checked($isCommercial)
                                   class="w-5 h-5">
                        </td>
                        <td class="px-3 py-2">{{ $site->id }}</td>
                        <td class="px-3 py-2">
                            <label for="keep_{{ $site->id }}" class="cursor-pointer">
                                {{ $site->name ?: $site->domain ?: '—' }}
                            </label>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600">
                            {{ optional($site->customer)->display_name ?? optional($site->customer)->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            @if ($isCommercial)
                                <span class="inline-block px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800">دارای خرید/لایسنس فعال</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">بدون اتکای تجاری</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white border rounded-xl p-4 space-y-3">
        <div>
            <label class="block font-medium mb-1 text-red-700">دلیل revoke (اجباری، حداقل ۵ نویسه)</label>
            <textarea name="revoke_reason" rows="2" minlength="5" maxlength="1000" required
                      class="form-control w-full"
                      placeholder="مثال: ماژول به‌صورت موقت برای رفع باگ امنیتی به حالت داخلی برگشت داده شد.">{{ old('revoke_reason', $oldRevokeReason) }}</textarea>
            @error('revoke_reason')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="confirmed_destructive" value="1" required class="w-5 h-5">
                <span class="text-sm">تأیید می‌کنم که این عملیات می‌تواند دسترسی سایت‌های UNCHECKED را به این ماژول غیرفعال کند و این تصمیم آگاهانه است.</span>
            </label>
            @error('confirmed_destructive')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex gap-2 pt-2">
        <button type="submit" class="btn btn-danger">اعمال تغییر (سایت‌های UNCHECKED revoke می‌شوند)</button>
        <a href="{{ route('admin.modules.edit', $module) }}" class="btn btn-secondary">انصراف و بازگشت</a>
    </div>
</form>
@endsection
