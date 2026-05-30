<div class="mb-3 text-end">
    <label class="form-label">نام *</label>
    <input type="text" name="name" class="form-control"
           placeholder="نام مشتری را وارد کنید"
           value="{{ old('name', $customer->name ?? '') }}" required>
</div>

<div class="mb-3 text-end">
    <label class="form-label">موبایل *</label>
    <input type="text" name="mobile" class="form-control"
           placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹"
           value="{{ old('mobile', $customer->mobile ?? '') }}" required dir="rtl">
</div>


<div class="mb-3 text-end">
    <label class="form-label">نام شرکت</label>
    <input type="text" name="company_name" class="form-control"
           placeholder="نام شرکت یا واحد"
           value="{{ old('company_name', $customer->company_name ?? '') }}">
</div>

@if(!empty($hasUserColumn))
<div class="mb-3 text-end">
    <label class="form-label">تخصیص به کاربر سایت (موبایل)</label>
    <select name="user_id" class="form-select">
        <option value="">— بدون تخصیص —</option>
        @foreach($users ?? [] as $u)
            <option value="{{ $u->id }}" {{ old('user_id', $customer->user_id ?? '') == $u->id ? 'selected' : '' }}>
                {{ $u->mobile }} @if($u->profile && $u->profile->full_name) ({{ $u->profile->full_name }}) @endif
            </option>
        @endforeach
    </select>
    <p class="text-gray-500 text-xs mt-1">کاربری که با این موبایل در سایت ثبت‌نام کرده را انتخاب کنید تا به این مشتری تخصیص داده شود.</p>
</div>
@else
<div class="mb-3 text-end p-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
    برای فعال‌سازی «تخصیص به کاربر سایت» در ترمینال اجرا کنید: <code class="bg-amber-100 px-1 rounded">php artisan migrate</code>
</div>
@endif

@if(!empty($hasResellerColumn) && isset($resellers))
<div class="mb-3 text-end">
    <label class="form-label">نماینده (Reseller)</label>
    <select name="reseller_id" class="form-select">
        <option value="">— بدون نماینده —</option>
        @foreach($resellers as $r)
            <option value="{{ $r->id }}" {{ old('reseller_id', $customer->reseller_id ?? '') == $r->id ? 'selected' : '' }}>
                {{ $r->company_name ?: $r->user->mobile ?? 'Reseller #'.$r->id }}
            </option>
        @endforeach
    </select>
</div>
@endif

<div class="mb-3 text-end">
    <label class="form-label">یادداشت</label>
    <textarea name="notes" class="form-control" rows="3"
              placeholder="یادداشت‌های مربوط به مشتری">{{ old('notes', $customer->notes ?? '') }}</textarea>
</div>
