@extends('layouts.admin')

@section('header', 'تأیید عملیات گروهی مخرب: تبدیل گروهی به «داخلی»')

@section('content')
{{--
    Phase 5 / Wave 5.2 / P0-1: صفحهٔ تأیید per-(site,module) برای bulk action.
    هر سطر یک جفت (site,module) است که در صورت confirmation revoke خواهد شد.
    سایت‌های دارای خرید/لایسنس فعال برای آن ماژول pre-checked Keep Active هستند.
--}}

<div class="mb-4 p-4 rounded-xl border-2 border-red-300 bg-red-50">
    <h3 class="font-bold text-red-800 text-lg mb-2">⚠ هشدار عملیات گروهی مخرب</h3>
    <p class="text-sm text-red-700 leading-relaxed">
        شما در حال انجام عملیات گروهی «تبدیل نوع به داخلی» روی {{ count($moduleIds) }} ماژول هستید.
        مجموع <strong>{{ count($affectedPairs) }}</strong> جفت (سایت × ماژول) تحت‌تأثیر قرار می‌گیرد.
    </p>
    <p class="text-sm text-red-700 mt-2 leading-relaxed">
        هیچ جفتی به‌صورت خودکار revoke نمی‌شود. سایت‌هایی که برای آن ماژول خرید یا لایسنس فعال دارند،
        به‌طور پیش‌فرض pre-checked «نگه‌دار فعال» هستند.
    </p>
</div>

<form method="POST" action="{{ route('admin.modules.bulk-update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="__confirm_step" value="apply">
    <input type="hidden" name="bulk_action" value="{{ $action }}">
    @foreach ($moduleIds as $mid)
        <input type="hidden" name="module_ids[]" value="{{ $mid }}">
    @endforeach

    <div class="bg-white border rounded-xl overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b">
            <h4 class="font-semibold">جفت‌های (سایت × ماژول) تحت‌تأثیر</h4>
            <p class="text-xs text-gray-600 mt-1">
                ✓ نگه‌دار فعال = این جفت revoke نمی‌شود. ☐ خالی = revoke خواهد شد.
            </p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-right">نگه‌دار فعال</th>
                    <th class="px-3 py-2 text-right">ماژول</th>
                    <th class="px-3 py-2 text-right">سایت</th>
                    <th class="px-3 py-2 text-right">مشتری</th>
                    <th class="px-3 py-2 text-right">وضعیت تجاری</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($affectedPairs as $pair)
                    @php
                        $module = $modules[$pair['module_id']] ?? null;
                        $site = $sites[$pair['site_id']] ?? null;
                        $key = $pair['site_id'] . ':' . $pair['module_id'];
                        $isCommercial = in_array($pair['site_id'], $commercialAttachmentsByModule[$pair['module_id']] ?? [], true);
                    @endphp
                    <tr class="border-t">
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox"
                                   name="keep_active_pair_keys[]"
                                   value="{{ $key }}"
                                   id="keep_{{ $key }}"
                                   @checked($isCommercial)
                                   class="w-5 h-5">
                        </td>
                        <td class="px-3 py-2">
                            <label for="keep_{{ $key }}" class="cursor-pointer">
                                {{ $module?->title ?: $module?->slug ?: ('Module #' . $pair['module_id']) }}
                            </label>
                        </td>
                        <td class="px-3 py-2">
                            #{{ $pair['site_id'] }} — {{ $site?->name ?: $site?->domain ?: '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600">
                            {{ optional($site?->customer)->display_name ?? optional($site?->customer)->name ?? '—' }}
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
                      placeholder="مثال: عملیات بازسازی کاتالوگ. ماژول‌های منتخب به‌صورت موقت داخلی شدند.">{{ old('revoke_reason', $oldRevokeReason) }}</textarea>
            @error('revoke_reason')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="confirmed_destructive" value="1" required class="w-5 h-5">
                <span class="text-sm">تأیید می‌کنم که این عملیات گروهی می‌تواند دسترسی جفت‌های UNCHECKED را غیرفعال کند.</span>
            </label>
            @error('confirmed_destructive')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex gap-2 pt-2">
        <button type="submit" class="btn btn-danger">اعمال تغییر گروهی (جفت‌های UNCHECKED revoke می‌شوند)</button>
        <a href="{{ route('admin.modules.index') }}" class="btn btn-secondary">انصراف و بازگشت</a>
    </div>
</form>
@endsection
