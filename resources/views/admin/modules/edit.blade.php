@extends('layouts.admin')

@section('header', 'ویرایش ماژول: ' . $module->title)

@section('content')
@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="mb-6 p-4 rounded-xl bg-indigo-50 border border-indigo-200">
    <h3 class="font-semibold text-indigo-900 mb-2">فروشگاه (مارکت‌پلیس)</h3>
    @if(isset($productInStore) && $productInStore)
        <p class="text-sm text-indigo-800 mb-2">این ماژول در فروشگاه به‌عنوان محصول تعریف شده است. پس از خرید توسط مشتری، به‌صورت خودکار به پنل ادمین او اضافه می‌شود.</p>
        <a href="{{ route('admin.products.edit', $productInStore) }}" class="btn btn-primary btn-sm">ویرایش محصول در فروشگاه</a>
    @else
        <p class="text-sm text-indigo-800 mb-2">برای اینکه مشتری بتواند این ماژول را از فروشگاه بخرد و به‌صورت خودکار به پنل او اضافه شود، یک محصول از نوع «ماژول افزودنی» تعریف کنید.</p>
        <a href="{{ route('admin.products.create', ['module_id' => $module->id]) }}" class="btn btn-primary btn-sm">افزودن به فروشگاه</a>
    @endif
</div>

<form method="POST" action="{{ route('admin.modules.update', $module) }}" class="max-w-2xl space-y-4">
    @csrf
    @method('PUT')

    <div>
        <label class="block font-medium mb-1">عنوان</label>
        <input type="text" name="title" value="{{ old('title', $module->title) }}" class="form-control w-full" required>
        @error('title')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block font-medium mb-1">نوع ماژول</label>
        @php
            $currentType = $module->type === \App\Models\Module::TYPE_INTERNAL
                ? 'internal'
                : ($module->type === \App\Models\Module::TYPE_PAID ? 'paid' : 'license');
            $selectedType = old('module_type', $currentType);
        @endphp
        <select name="module_type" id="module_type_select" class="form-control w-full" data-original-type="{{ $currentType }}">
            <option value="internal" {{ $selectedType === 'internal' ? 'selected' : '' }}>داخلی (فقط پنل مدیر اصلی)</option>
            <option value="license" {{ $selectedType === 'license' ? 'selected' : '' }}>مبتنی بر لایسنس (غیرقابل خرید مستقیم)</option>
            <option value="paid" {{ $selectedType === 'paid' ? 'selected' : '' }}>پولی (قابل خرید توسط مشتری)</option>
        </select>
        <p class="text-xs text-slate-500 mt-1">
            تغییر نوع ماژول فقط نحوهٔ دسترسی سایت‌های مشتری را عوض می‌کند؛ داده‌ها و محتوا هرگز حذف نمی‌شوند.
        </p>
        @error('module_type')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    @php
        // Phase 5 / Wave 5.2 / P0-1: تعداد سایت‌های فعال روی این ماژول (برای نمایش در «منطقه خطرناک»).
        $activeSitesOnModule = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            $activeSitesOnModule = (int) \Illuminate\Support\Facades\DB::table('site_modules')
                ->where('module_id', $module->id)
                ->where('is_active', true)
                ->count();
        }
    @endphp

    {{-- Phase 5 / Wave 5.2 / P0-1: کادر «منطقه خطرناک» — فقط زمانی نمایش/فعال می‌شود که نوع به «داخلی» تبدیل شود و سایت فعال داشته باشد. --}}
    <div id="destructive-zone"
         class="p-4 rounded-lg border border-red-300 bg-red-50 space-y-3"
         style="display: {{ ($selectedType === 'internal' && $currentType !== 'internal' && $activeSitesOnModule > 0) ? 'block' : 'none' }};"
         data-active-sites="{{ $activeSitesOnModule }}">
        <h4 class="font-semibold text-red-800">⚠ عملیات مخرب — تأیید صریح لازم است</h4>
        <p class="text-sm text-red-700">
            با تبدیل این ماژول به «داخلی»، دسترسی <strong id="dz-site-count">{{ $activeSitesOnModule }}</strong> سایت فعال
            به آن قطع می‌شود و رابط کاربری مخفی می‌شود. <strong>هیچ داده‌ای پاک نمی‌شود</strong> و در صورت بازگردانی نوع،
            دسترسی قبلی قابل بازگشت است. هر سایت affected یک رکورد revoke صریح در دفترکل (`module_entitlements`) ثبت خواهد کرد.
        </p>
        <div>
            <label class="block text-sm font-medium text-red-800 mb-1">دلیل revoke (اجباری، حداقل ۵ نویسه)</label>
            <textarea name="revoke_reason" rows="2" maxlength="1000"
                      class="form-control w-full border-red-300"
                      placeholder="مثال: ماژول از کاتالوگ بازنشسته شد و در نسخهٔ بعدی پشتیبانی نمی‌شود.">{{ old('revoke_reason') }}</textarea>
            @error('revoke_reason')<p class="text-red-700 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-red-800">
            <input type="checkbox" name="confirmed_destructive" value="1" {{ old('confirmed_destructive') ? 'checked' : '' }}>
            با اطلاع کامل از پیامد بالا، تأیید می‌کنم که این عملیات مخرب اعمال شود.
        </label>
        @error('confirmed_destructive')<p class="text-red-700 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block font-medium mb-1">توضیح کامل</label>
        <textarea name="description" rows="3" class="form-control w-full">{{ old('description', $module->description) }}</textarea>
    </div>

    @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'short_description'))
    <div>
        <label class="block font-medium mb-1">توضیح کوتاه (مارکت‌پلیس)</label>
        <textarea name="short_description" rows="2" class="form-control w-full" maxlength="500">{{ old('short_description', $module->short_description) }}</textarea>
    </div>
    @endif

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block font-medium mb-1">قیمت (ریال)</label>
            <input type="number" name="price_irr" value="{{ old('price_irr', $module->price_irr) }}" class="form-control w-full" min="0" step="1" placeholder="۰ = رایگان">
        </div>
        @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'price_promo_irr'))
        <div>
            <label class="block font-medium mb-1">قیمت پرومو (ریال)</label>
            <input type="number" name="price_promo_irr" value="{{ old('price_promo_irr', $module->price_promo_irr) }}" class="form-control w-full" min="0" step="1" placeholder="خالی = بدون پرومو">
        </div>
        @endif
    </div>

    @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'license_type_default'))
    <div>
        <label class="block font-medium mb-1">نوع لایسنس پیش‌فرض</label>
        <select name="license_type_default" class="form-control w-full">
            <option value="one_time" {{ old('license_type_default', $module->license_type_default ?? 'one_time') === 'one_time' ? 'selected' : '' }}>یک‌بار خرید</option>
            <option value="subscription" {{ old('license_type_default', $module->license_type_default ?? 'one_time') === 'subscription' ? 'selected' : '' }}>اشتراک</option>
        </select>
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'free_assignable'))
    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
        <h4 class="font-medium text-slate-800 mb-2">نحوهٔ توزیع ماژول</h4>
        <label class="flex items-center gap-2 mb-2">
            <input type="checkbox" name="free_assignable" value="1" {{ old('free_assignable', $module->free_assignable ?? false) ? 'checked' : '' }}>
            قابل اختصاص رایگان — ادمین می‌تواند در «ماژول‌های سایت» به‌صورت دستی برای tenant فعال کند (بدون پرداخت و لایسنس)
        </label>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="license_required" value="1" {{ old('license_required', $module->license_required ?? false) ? 'checked' : '' }}>
            فقط با لایسنس — در مارکت‌پلیس نمایش داده نمی‌شود؛ فعال‌سازی فقط با صدور لایسنس توسط ادمین
        </label>
        <p class="text-xs text-slate-500 mt-2">اگر قیمت داشته باشد و «فقط با لایسنس» نزده شود، ماژول فقط در فروشگاه tenant قابل خرید است.</p>
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'marketplace_visible'))
    <div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="marketplace_visible" value="1" {{ old('marketplace_visible', $module->marketplace_visible ?? true) ? 'checked' : '' }}>
            نمایش در فروشگاه (مارکت‌پلیس) — برای ماژول‌های پولی
        </label>
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'category'))
    <div>
        <label class="block font-medium mb-1">دسته‌بندی مارکت‌پلیس</label>
        <input type="text" name="category" value="{{ old('category', $module->category) }}" class="form-control w-full" placeholder="مثلاً فروشگاه، فرم، اعلان">
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'screenshots'))
    <div>
        <label class="block font-medium mb-1">اسکرین‌شات‌ها (هر URL در یک خط)</label>
        <textarea name="screenshots" rows="3" class="form-control w-full" placeholder="https://...">@if($module->screenshots){{ implode("\n", $module->screenshots) }}@endif</textarea>
    </div>
    @endif

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="{{ route('admin.modules.index') }}" class="btn btn-secondary">بازگشت</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[action="{{ route('admin.modules.update', $module) }}"]');
    if (!form) return;
    var select = form.querySelector('select[name="module_type"]');
    if (!select) return;
    var originalType = select.getAttribute('data-original-type') || '';
    var destructiveZone = document.getElementById('destructive-zone');
    var activeSites = destructiveZone ? parseInt(destructiveZone.getAttribute('data-active-sites') || '0', 10) : 0;

    // Phase 5 / Wave 5.2 / P0-1: کادر «منطقه خطرناک» فقط زمانی نمایش داده شود که در حال تبدیل به internal با سایت‌های فعال هستیم.
    function syncDestructiveZone() {
        if (!destructiveZone) return;
        var isDestructive = (select.value === 'internal' && originalType !== 'internal' && activeSites > 0);
        destructiveZone.style.display = isDestructive ? 'block' : 'none';
    }
    select.addEventListener('change', syncDestructiveZone);

    form.addEventListener('submit', function (e) {
        var current = select.value;
        // اگر کادر مخرب فعال است، server-side خود ولیداسیون می‌کند؛ نیازی به prompt JS نیست.
        if (current === originalType) return;
        if (current === 'internal' && originalType !== 'internal' && activeSites > 0) {
            // ولیداسیون اضافی client-side: اگر چک‌باکس نزده، اجازه نده submit شود.
            var cb = form.querySelector('input[name="confirmed_destructive"]');
            var rr = form.querySelector('textarea[name="revoke_reason"]');
            if (!cb || !cb.checked || !rr || rr.value.trim().length < 5) {
                e.preventDefault();
                window.alert('برای تبدیل این ماژول به «داخلی»، کادر منطقه خطرناک را تکمیل کنید: دلیل revoke (حداقل ۵ نویسه) + تیک تأیید عملیات مخرب.');
                if (rr) rr.focus();
                return;
            }
            return; // server-side validation final ساز است
        }
        // تغییرات غیر-مخرب (مثلاً paid↔license): فقط confirm ساده.
        var message = 'با تغییر نوع ماژول، نحوهٔ دسترسی سایت‌های مشتری عوض می‌شود.\n' +
            '• تبدیل به ماژول لایسنس‌محور یا پولی = سایت‌های فعلی دسترسی را نگه می‌دارند، اما قواعد فعال‌سازی بعدی عوض می‌شود.\n\n' +
            'آیا از ادامه مطمئن هستید؟';
        if (!window.confirm(message)) {
            e.preventDefault();
        }
    });
});
</script>
@endsection
