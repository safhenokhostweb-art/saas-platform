@extends('layouts.admin')

@section('header', 'ماژول‌ها')

@section('content')
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای ماژول‌ها', 'updated' => '۱۴۰۴/۱۲/۰۸', 'id' => 'modules'])
    <p><strong>سه نوع ماژول:</strong> (۱) <strong>داخلی</strong> — فقط برای پنل مدیر اصلی؛ هرگز به سایت‌های مشتری ارسال نمی‌شود؛ (۲) <strong>مبتنی بر لایسنس</strong> (type = core) — همراه لایسنس اصلی سایت قابل اختصاص است و در فروشگاه ماژول فروخته نمی‌شود؛ (۳) <strong>پولی</strong> (type = paid) — فقط از طریق فروشگاه ماژول در پنل مشتری قابل خرید است و پس از خرید خودکار فعال می‌شود.</p>
    <p><strong>حالت‌های توزیع:</strong> با تیک‌های «قابل اختصاص رایگان»، «فقط با لایسنس»، قیمت و «نمایش در فروشگاه» می‌توانید تعیین کنید ماژول چگونه به سایت‌ها برسد (تخصیص دستی، فقط با لایسنس ماژول، یا فقط از مارکت‌پلیس).</p>
    <p>تغییر نوع ماژول از «مبتنی بر لایسنس» یا «پولی» به «داخلی» فقط دسترسی سایت‌ها را غیرفعال و رابط کاربری را مخفی می‌کند؛ <strong>هیچ داده‌ای از سایت مشتری حذف نمی‌شود</strong> و در صورت بازگردانی نوع، محتوای قبلی دوباره در دسترس خواهد بود.</p>
@endcomponent

<form method="POST" action="{{ route('admin.modules.bulk-update') }}" class="mt-4 bg-white rounded-2xl shadow overflow-hidden">
    @csrf
    <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50 text-xs text-gray-700">
        <div class="flex items-center gap-2">
            <label for="bulk_action" class="font-medium">اعمال گروهی روی ماژول‌های انتخاب‌شده:</label>
            <select name="bulk_action" id="bulk_action" class="form-select text-xs">
                <option value="">انتخاب کنید…</option>
                <option value="set_type_internal">تبدیل نوع به «داخلی» (internal)</option>
                <option value="set_type_license">تبدیل نوع به «مبتنی بر لایسنس» (core)</option>
                <option value="set_type_paid">تبدیل نوع به «پولی» (paid)</option>
                <option value="marketplace_on">نمایش در فروشگاه (مارکت‌پلیس) برای همه</option>
                <option value="marketplace_off">عدم نمایش در فروشگاه برای همه</option>
                <option value="free_assignable_on">فعال‌کردن «قابل اختصاص رایگان»</option>
                <option value="free_assignable_off">غیرفعال‌کردن «قابل اختصاص رایگان»</option>
                <option value="license_required_on">فعال‌کردن «فقط با لایسنس»</option>
                <option value="license_required_off">غیرفعال‌کردن «فقط با لایسنس»</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-xs"
                    onclick="return window.confirm('تغییرات گروهی روی ماژول‌های انتخاب‌شده اعمال می‌شود. در صورت تبدیل نوع به «داخلی»، دسترسی سایت‌ها به این ماژول‌ها غیرفعال و رابط کاربری آن‌ها مخفی می‌شود، اما هیچ داده‌ای حذف نخواهد شد. ادامه می‌دهید؟');">
                اعمال
            </button>
        </div>
        <div class="text-gray-500">
            ابتدا ماژول‌ها را از ستون اول تیک بزنید، سپس نوع یا تنظیم موردنظر را انتخاب کنید.
        </div>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right w-10">
                    <input type="checkbox" onclick="document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = this.checked);">
                </th>
                <th class="p-3 text-right">عنوان / slug</th>
                <th class="p-3 text-right">نسخه</th>
                <th class="p-3 text-right">نوع / حالت توزیع</th>
                <th class="p-3 text-right">قیمت (ریال)</th>
                <th class="p-3 text-right">سایت‌های فعال</th>
                @if(isset($licensedCounts))<th class="p-3 text-right">لایسنس فعال</th>@endif
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($modules as $module)
                @php $activeCount = $moduleSiteCounts[$module->id] ?? 0; @endphp
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3 align-top">
                        <input type="checkbox" name="module_ids[]" value="{{ $module->id }}" class="module-checkbox">
                    </td>
                    <td class="p-3">
                        <span class="font-medium">{{ $module->title }}</span>
                        @if($module->slug)
                            <span class="block text-xs text-gray-500">{{ $module->slug }}</span>
                        @endif
                    </td>
                    <td class="p-3">{{ $module->version ?? '—' }}</td>
                    <td class="p-3">
                        @if($module->type === 'core')
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">لایسنس‌محور</span>
                        @elseif($module->type === 'paid')
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">پولی</span>
                        @elseif($module->type === 'internal')
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-800 text-white">داخلی</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $module->type ?? '—' }}</span>
                        @endif
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'free_assignable'))
                            @php $mode = $module->getDistributionMode(); @endphp
                            @if($mode === 'free_assignable')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 mr-1" title="قابل اختصاص دستی در سایت‌ها → ماژول‌ها">رایگان</span>
                            @elseif($mode === 'paid_marketplace')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 mr-1" title="فقط در فروشگاه ماژول پنل مشتری">مارکت</span>
                            @elseif($mode === 'license_only')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800 mr-1" title="فقط با صدور لایسنس">لایسنس</span>
                            @elseif($mode === 'disabled')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">غیرفعال</span>
                            @endif
                        @endif
                    </td>
                    <td class="p-3">
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('modules', 'price_irr'))
                            @php $productForModule = $productByModuleId[$module->id] ?? null; $displayPrice = $productForModule ? $productForModule->price_irr : ($module->price_irr ?? 0); @endphp
                            <span class="font-medium text-gray-800">{{ $displayPrice ? number_format($displayPrice) : '۰' }}</span>
                            <span class="text-gray-500 text-xs">ریال</span>
                            @if($productForModule)
                                <span class="block text-xs text-green-600">در فروشگاه</span>
                            @else
                                <a href="{{ route('admin.modules.edit', $module) }}" class="block text-xs text-indigo-600 hover:underline">درج قیمت</a>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="p-3">{{ $activeCount }} از {{ $sitesCount }}</td>
                    @if(isset($licensedCounts))<td class="p-3">{{ $licensedCounts[$module->id] ?? ($module->type === 'paid' ? '۰' : '—') }}</td>@endif
                    <td class="p-3">
                        <a href="{{ route('admin.sites.index') }}?module={{ $module->slug }}" class="text-indigo-600 hover:underline text-xs ml-2">سایت‌ها</a>
                        <a href="{{ route('admin.modules.edit', $module) }}" class="text-indigo-600 hover:underline text-xs ml-2">ویرایش</a>
                        @if(isset($licensedCounts))<a href="{{ route('admin.module-licenses.index', ['module_id' => $module->id]) }}" class="text-indigo-600 hover:underline text-xs ml-2">لایسنس‌ها</a>@endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ isset($licensedCounts) ? 8 : 7 }}" class="p-6 text-center text-gray-500">ماژولی تعریف نشده است.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</form>
@endsection
