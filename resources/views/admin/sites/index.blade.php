@extends('layouts.admin')

@section('header','سایت‌ها')

@section('content')
@include('admin.sites.guide')
<div class="flex flex-wrap gap-2 items-center">
    <a href="{{ route('admin.sites.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
        افزودن سایت
    </a>
    @if(\App\Helpers\PlatformModules::isEnabled('notifications'))
    <a href="{{ route('admin.sites.notifications.create-bulk') }}" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded hover:bg-slate-50">
        ارسال اعلان به چند سایت
    </a>
    @endif
</div>
@if(isset($filterCustomer) && $filterCustomer)
    <p class="mt-2 text-sm text-gray-600">
        نمایش سایت‌های مشتری: <strong>{{ $filterCustomer->company_name ?? $filterCustomer->name }}</strong>
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 mr-2">نمایش همه</a>
    </p>
@endif
@if(isset($filterModule) && $filterModule)
    <p class="mt-2 text-sm text-gray-600">
        فیلتر ماژول: <strong>{{ $filterModule->title }}</strong>
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 mr-2">نمایش همه</a>
    </p>
@endif

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">عنوان / دامنه</th>
                <th class="p-3 text-right">مشتری</th>
                <th class="p-3 text-right">ماژول‌های خریداری‌شده</th>
                <th class="p-3 text-right">انقضای پشتیبانی</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sites as $site)
                @php $supportStatus = $site->supportStatus(); @endphp
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        <span class="font-medium">{{ $site->title }}</span>
                        @if($site->domain)
                            <span class="text-gray-500 block text-xs">{{ $site->domain }}</span>
                        @endif
                    </td>
                    <td class="p-3">
                        <a href="{{ route('admin.customers.edit', $site->customer) }}" class="text-indigo-600 hover:underline">{{ $site->customer->company_name ?? $site->customer->name }}</a>
                    </td>
                    <td class="p-3">
                        @php $allModules = \App\Models\Module::where('is_active', true)->get(); @endphp
                        @if($allModules->isEmpty())
                            <span class="text-gray-400">—</span>
                        @else
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach($allModules as $mod)
                                    @php $siteMod = $site->modules->firstWhere('id', $mod->id); $isActive = $siteMod && $siteMod->pivot->is_active; @endphp
                                    <form method="post" action="{{ route('admin.sites.modules.toggle', $site) }}" class="inline" data-module="{{ $mod->slug }}">
                                        @csrf
                                        <input type="hidden" name="module_id" value="{{ $mod->id }}">
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border transition {{ $isActive ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-500' }}" title="{{ $isActive ? 'غیرفعال' : 'فعال' }}">
                                            <span class="w-2 h-2 rounded-full {{ $isActive ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                            {{ $mod->title }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                            <a href="{{ route('admin.sites.modules.edit', $site) }}" class="text-indigo-600 text-xs block mt-1">مدیریت کامل ماژول‌ها</a>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($site->support_expires_at)
                            <span class="
                                @if($supportStatus === 'expired') text-red-600 font-medium
                                @elseif($supportStatus === 'expiring_soon') text-amber-600
                                @else text-gray-600 @endif">
                                {{ \App\Helpers\Jalali::format($site->support_expires_at) }}
                            </span>
                            @if($supportStatus === 'expired')
                                <span class="block text-xs text-red-600">منقضی شده</span>
                            @elseif($supportStatus === 'expiring_soon')
                                <span class="block text-xs text-amber-600">به‌زودی منقضی</span>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3">
                        <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">ویرایش</a>
                        @if(\App\Helpers\PlatformModules::isEnabled('notifications'))
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.sites.notifications.index', $site) }}" class="text-indigo-600 hover:underline" title="ارسال اعلان به این سایت">اعلان‌ها</a>
                        @endif
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.sites.modules.edit', $site) }}" class="text-indigo-600 hover:underline">ماژول‌ها</a>
                        @if(\App\Helpers\AdminMenu::isVisible('sites_menus'))
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.sites.menus.index', $site) }}" class="text-indigo-600 hover:underline" title="منو و ناوبری">منوها</a>
                        @endif
                        @if(\Illuminate\Support\Facades\Schema::hasTable('seo_redirects'))
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.sites.seo.index', $site) }}" class="text-indigo-600 hover:underline" title="سئو پیشرفته">سئو</a>
                        @endif
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.sites.updates.index', $site) }}" class="text-indigo-600 hover:underline">بروزرسانی</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.sites.license.create', $site) }}" class="text-indigo-600 hover:underline">لایسنس</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.captcha.site', $site) }}" class="text-indigo-600 hover:underline">کپچا</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
