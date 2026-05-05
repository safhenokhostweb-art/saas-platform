@extends('layouts.admin')

@section('header', 'داشبورد')


@section('content')

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <!-- Customers -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="text-gray-500 text-sm">تعداد مشتری‌ها</div>
        <div class="text-3xl font-bold mt-2">{{ $customers }}</div>
    </div>

    <!-- Sites -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="text-gray-500 text-sm">تعداد سایت‌ها</div>
        <div class="text-3xl font-bold mt-2">{{ $sites }}</div>
    </div>

    <!-- Forms -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="text-gray-500 text-sm">تعداد فرم‌ها</div>
        <div class="text-3xl font-bold mt-2">{{ $forms }}</div>
    </div>

</div>

<!-- هشدارهای انقضا (اعلان خودکار در ادمین اصلی) -->
@if(isset($expiryReminders) && $expiryReminders->isNotEmpty())
<div class="bg-white rounded-2xl shadow p-6 mb-8 border-r-4 border-amber-400">
    <h2 class="font-bold mb-2">هشدارهای انقضا</h2>
    <p class="text-gray-500 text-sm mb-4">اعلان خودکار انقضای پشتیبانی، هاست و دامنه (۱ ماه قبل و هر ۱۰ روز) — در <a href="{{ route('admin.platform-settings.index') }}" class="text-indigo-600 hover:underline">تنظیمات پلتفرم</a> می‌توانید ارسال را فعال/غیرفعال کنید.</p>
    <ul class="space-y-2 max-h-64 overflow-y-auto">
        @foreach($expiryReminders as $reminder)
            <li class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-100">
                <span class="shrink-0 w-2 h-2 mt-1.5 rounded-full {{ $reminder->type === 'support' ? 'bg-blue-500' : ($reminder->type === 'host' ? 'bg-purple-500' : 'bg-teal-500') }}" title="{{ $reminder->type === 'support' ? 'پشتیبانی' : ($reminder->type === 'host' ? 'هاست' : 'دامنه') }}"></span>
                <div class="min-w-0 flex-1">
                    <span class="font-medium text-gray-800">{{ $reminder->title ?? 'هشدار انقضا' }}</span>
                    @if($reminder->body)
                        <p class="text-sm text-gray-600 mt-0.5">{{ Str::limit($reminder->body, 100) }}</p>
                    @endif
                    <p class="text-xs text-gray-500 mt-1">{{ $reminder->sent_at ? \App\Helpers\Jalali::formatFa($reminder->sent_at, 'Y/m/d H:i') : '' }}</p>
                </div>
                @if($reminder->site)
                    <a href="{{ route('admin.sites.edit', $reminder->site) }}" class="shrink-0 text-sm text-indigo-600 hover:underline">ویرایش سایت</a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endif

<!-- دسترسی سریع: مدیریت سایت‌ها (تِننت‌ها)، ماژول‌ها، لایسنس، بروزرسانی -->
<div class="bg-white rounded-2xl shadow p-6 mb-8">
    <h2 class="font-bold mb-4">مدیریت سایت‌ها و سرویس‌ها</h2>
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.sites.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
            سایت‌ها (تِننت‌ها)
        </a>
        <a href="{{ route('admin.modules.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            ماژول‌ها
        </a>
        <a href="{{ route('admin.licenses.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            لایسنس‌ها
        </a>
        <a href="{{ route('admin.updates.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            داشبورد بروزرسانی‌ها
        </a>
    </div>
</div>

<!-- فرم‌ها و وضعیت در سایت‌ها (فقط در پنل ادمین) -->
@if(isset($formSiteColumnExists))
@php
    $formsList = $formsList ?? collect();
    $sitesList = $sitesList ?? collect();
@endphp
<div class="bg-white rounded-2xl shadow p-6 mb-8">
    <h2 class="font-bold mb-4">فرم‌ها و فعال/غیرفعال در سایت مشتری</h2>
    @if(!$formSiteColumnExists)
        <div class="alert alert-danger">
            ستون <code>is_active</code> در جدول <code>form_site</code> یافت نشد. برای نمایش این بخش در ترمینال اجرا کنید:
            <pre class="mt-2 p-3 bg-gray-100 rounded text-sm">php artisan migrate</pre>
        </div>
    @else
        <p class="text-gray-500 text-sm mb-4">برای هر فرم می‌توانید در هر سایت مشتری آن را فعال یا غیرفعال کنید. فرم غیرفعال در آن سایت نمایش داده نمی‌شود.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold">فرم</th>
                        @foreach($sitesList as $site)
                            <th class="p-3 font-bold whitespace-nowrap">{{ $site->title }}</th>
                        @endforeach
                        <th class="p-3 font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formsList as $form)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3">{{ $form->title }}</td>
                            @foreach($sitesList as $site)
                                @php
                                    $pivot = $form->sites->firstWhere('id', $site->id);
                                    $isActive = $pivot ? (bool) ($pivot->pivot->is_active ?? true) : false;
                                @endphp
                                <td class="p-3">
                                    <form action="{{ route('admin.forms.sites.toggle', [$form, $site]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 rounded text-sm {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}" title="{{ $isActive ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                            @if($isActive)
                                                <span aria-hidden="true">✓</span> فعال
                                            @else
                                                <span aria-hidden="true">—</span> غیرفعال
                                            @endif
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                            <td class="p-3">
                                <a href="{{ route('admin.forms.sites.edit', $form) }}" class="text-blue-600 hover:underline text-sm">تنظیم سایت‌ها</a>
                                <span class="mx-1">|</span>
                                <a href="{{ route('admin.forms.edit', $form) }}" class="text-blue-600 hover:underline text-sm">ویرایش فرم</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($sitesList) + 2 }}" class="p-4 text-gray-500 text-center">هنوز فرمی تعریف نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

<!-- وضعیت ماژول‌های سایت‌ها (چک‌لیست امکاناتی که به هر سایت مشتری داده شده) -->
@if(isset($sitesWithModules) && isset($allModules))
<div class="bg-white rounded-2xl shadow p-6 mb-8">
    <h2 class="font-bold mb-2">وضعیت ماژول‌های سایت‌های مشتری</h2>
    <p class="text-gray-500 text-sm mb-4">ماژول‌هایی که از پنل ادمین به هر سایت داده شده و در API لایسنس/سایت‌کانفیگ به آن سایت برگردانده می‌شوند. برای تغییر، از منوی سایت‌ها وارد هر سایت شوید و ماژول‌ها را مدیریت کنید.</p>
    @if($allModules->isEmpty())
        <p class="text-gray-500 text-sm">هنوز ماژولی در سیستم تعریف نشده است. از منوی ماژول‌ها یا سایت‌ها ماژول اضافه کنید.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold">سایت</th>
                        <th class="p-3 font-bold">دامنه</th>
                        @foreach($allModules as $mod)
                            <th class="p-3 font-bold whitespace-nowrap" title="{{ $mod->title ?? $mod->slug }}">{{ $mod->slug }}</th>
                        @endforeach
                        <th class="p-3 font-bold">وضعیت سایت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sitesWithModules as $site)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3">
                                <a href="{{ route('admin.sites.show', $site) }}" class="text-blue-600 hover:underline">{{ $site->title }}</a>
                                <span class="mx-1">|</span>
                                <a href="{{ route('admin.sites.modules.edit', $site) }}" class="text-sm text-gray-600 hover:underline">ماژول‌ها</a>
                            </td>
                            <td class="p-3 text-sm text-gray-600">{{ $site->domain ?? '—' }}</td>
                            @foreach($allModules as $mod)
                                @php
                                    $attached = $site->modules->contains('id', $mod->id);
                                @endphp
                                <td class="p-3">
                                    @if($attached)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-sm bg-green-100 text-green-800" title="فعال برای این سایت">✓</span>
                                    @else
                                        <span class="text-gray-300" title="غیرفعال">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="p-3">
                                @if($site->is_active)
                                    <span class="text-green-600 text-sm">فعال</span>
                                @else
                                    <span class="text-red-600 text-sm">غیرفعال</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $allModules->count() + 3 }}" class="p-4 text-gray-500 text-center">هنوز سایتی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

@include('admin.dashboard.guide')

<!-- Chart Section -->
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="font-bold mb-4">آمار کلی سیستم</h2>
    <canvas id="statsChart"></canvas>
</div>

@endsection


@section('scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        const ctx = document.getElementById('statsChart').getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['مشتری‌ها', 'سایت‌ها', 'فرم‌ها'],
                datasets: [{
                    label: 'آمار سیستم',
                    data: @json([$customers, $sites, $forms]),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

    });
</script>

@endsection
