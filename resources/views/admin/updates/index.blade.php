@extends('layouts.admin')

@section('header', 'داشبورد بروزرسانی‌ها')

@section('content')
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای بروزرسانی', 'updated' => '۱۴۰۴/۱۲/۰۸', 'id' => 'updates'])
    <p>وضعیت بروزرسانی ماژول‌ها برای هر سایت. سایت‌های واجد شرایط (دارای پشتیبانی فعال) می‌توانند بروزرسانی دریافت کنند. برای توزیع بروزرسانی دستی، از منوی سایت‌ها وارد هر سایت شوید و از لینک «بروزرسانی» استفاده کنید.</p>
@endcomponent

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold mb-4">وضعیت بروزرسانی سایت‌ها</h2>
        <div class="space-y-3 max-h-96 overflow-y-auto">
            @forelse($sites as $site)
                @php $status = $siteStatuses[$site->id] ?? ['eligible' => false, 'updates' => []]; @endphp
                <div class="flex items-center justify-between p-3 rounded-lg border {{ $status['eligible'] ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-gray-200' }}">
                    <div>
                        <a href="{{ route('admin.sites.edit', $site) }}" class="font-medium text-indigo-600 hover:underline">{{ $site->title }}</a>
                        <span class="block text-xs text-gray-500">{{ $site->domain ?? '—' }}</span>
                    </div>
                    <div class="text-left">
                        @if($status['eligible'])
                            @if(count($status['updates']) > 0)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">{{ count($status['updates']) }} بروزرسانی</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">به‌روز</span>
                            @endif
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">غیرفعال</span>
                        @endif
                        <a href="{{ route('admin.sites.updates.index', $site) }}" class="block text-xs text-indigo-600 mt-1 hover:underline">جزئیات</a>
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm">سایتی ثبت نشده است.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold mb-4">آخرین توزیع‌های بروزرسانی</h2>
        @if($recentSiteUpdates->isEmpty())
            <p class="text-gray-500 text-sm">هنوز توزیعی ثبت نشده است.</p>
        @else
            <div class="space-y-2">
                @foreach($recentSiteUpdates as $up)
                    <div class="flex items-center justify-between p-2 rounded border border-gray-100">
                        <div>
                            <span class="font-medium">{{ $up->version_label }}</span>
                            <span class="text-gray-500 text-sm"> — {{ $up->site->title ?? '—' }}</span>
                        </div>
                        <span class="text-xs text-gray-500">{{ $up->distributed_at ? \App\Helpers\Jalali::formatFa($up->distributed_at) : '—' }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('admin.sites.index') }}" class="block mt-4 text-indigo-600 text-sm hover:underline">مشاهده همه سایت‌ها</a>
        @endif
    </div>
</div>

@if($allModules->isNotEmpty())
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="font-bold mb-4">ماژول‌های دارای بروزرسانی (به تفکیک سایت)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3 text-right">سایت</th>
                    <th class="p-3 text-right">ماژول‌های قدیمی</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sites as $site)
                    @php $status = $siteStatuses[$site->id] ?? ['updates' => []]; @endphp
                    @if(count($status['updates']) > 0)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3">
                                <a href="{{ route('admin.sites.updates.index', $site) }}" class="text-indigo-600 hover:underline">{{ $site->title }}</a>
                            </td>
                            <td class="p-3">
                                @foreach($status['updates'] as $u)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 mr-1">
                                        {{ $u['title'] ?? $u['slug'] }} ({{ $u['installed_version'] ?? '—' }} → {{ $u['available_version'] ?? '—' }})
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
