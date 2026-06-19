@extends('layouts.admin')

@section('header', 'لایسنس‌ها')

@section('content')
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای لایسنس‌ها', 'updated' => '۱۴۰۴/۱۲/۰۸', 'id' => 'licenses'])
    <p>لیست لایسنس‌های صادرشده برای سایت‌های مشتری. کلید لایسنس فقط یک‌بار هنگام صدور نمایش داده می‌شود. برای صدور لایسنس جدید، از منوی <strong>سایت‌ها</strong> وارد سایت موردنظر شوید و از لینک «لایسنس» استفاده کنید. از این صفحه می‌توانید لایسنس را <strong>غیرفعال (ابطال)</strong> یا دوباره <strong>فعال</strong> کنید؛ لایسنس‌های ابطال‌شده را می‌توان حذف دائمی کرد.</p>
@endcomponent

<div class="flex flex-wrap gap-2 mt-4 mb-4">
    <a href="{{ route('admin.licenses.index') }}" class="px-3 py-1.5 rounded text-sm {{ !request('status') ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">همه</a>
    <a href="{{ route('admin.licenses.index', ['status' => 'active']) }}" class="px-3 py-1.5 rounded text-sm {{ request('status') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">فعال</a>
    <a href="{{ route('admin.licenses.index', ['status' => 'expired']) }}" class="px-3 py-1.5 rounded text-sm {{ request('status') === 'expired' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">منقضی</a>
    <a href="{{ route('admin.licenses.index', ['status' => 'revoked']) }}" class="px-3 py-1.5 rounded text-sm {{ request('status') === 'revoked' ? 'bg-gray-200 text-gray-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">ابطال‌شده</a>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">سایت / مشتری</th>
                <th class="p-3 text-right">پیشوند کلید</th>
                <th class="p-3 text-right">صادر شده</th>
                <th class="p-3 text-right">انقضا</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($licenses as $license)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        @if($license->site)
                            <a href="{{ route('admin.sites.edit', $license->site) }}" class="font-medium text-indigo-600 hover:underline">{{ $license->site->title }}</a>
                            <span class="block text-xs text-gray-500">{{ $license->site->customer->company_name ?? $license->site->customer->name ?? '—' }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="p-3 font-mono text-xs">{{ $license->license_key_prefix ?? '—' }}</td>
                    <td class="p-3">{{ $license->issued_at ? \App\Helpers\Jalali::formatFa($license->issued_at) : '—' }}</td>
                    <td class="p-3">{{ $license->expires_at ? \App\Helpers\Jalali::formatFa($license->expires_at) : 'بدون انقضا' }}</td>
                    <td class="p-3">
                        @if($license->isRevoked())
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-700">ابطال‌شده</span>
                        @elseif($license->isExpired())
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">منقضی</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">فعال</span>
                        @endif
                    </td>
                    <td class="p-3 flex flex-wrap gap-2 items-center">
                        @if($license->site)
                            <a href="{{ route('admin.sites.edit', $license->site) }}" class="text-indigo-600 hover:underline text-xs">سایت</a>
                        @endif
                        @if(!$license->isRevoked() && !$license->isExpired())
                            <form action="{{ route('admin.licenses.revoke', ['platform_license' => $license]) }}" method="post" class="inline" onsubmit="return confirm('لایسنس این سایت غیرفعال می‌شود و سایت با این کلید دیگر معتبر نخواهد بود. ادامه می‌دهید؟');">
                                @csrf
                                @foreach(request()->only('status', 'site_id') as $k => $v)
                                    @if($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                                @endforeach
                                <button type="submit" class="text-amber-600 hover:underline text-xs">غیرفعال کردن</button>
                            </form>
                        @endif
                        @if($license->isRevoked())
                            <form action="{{ route('admin.licenses.reactivate', ['platform_license' => $license]) }}" method="post" class="inline">
                                @csrf
                                @foreach(request()->only('status', 'site_id') as $k => $v)
                                    @if($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                                @endforeach
                                <button type="submit" class="text-green-600 hover:underline text-xs">فعال‌سازی مجدد</button>
                            </form>
                            <form action="{{ route('admin.licenses.destroy', ['platform_license' => $license]) }}" method="post" class="inline" onsubmit="return confirm('رکورد این لایسنس برای همیشه حذف می‌شود. ادامه می‌دهید؟');">
                                @csrf
                                @method('DELETE')
                                @foreach(request()->only('status', 'site_id') as $k => $v)
                                    @if($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                                @endforeach
                                <button type="submit" class="text-red-600 hover:underline text-xs">حذف دائمی</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-500">لایسنسی صادر نشده است.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($licenses->hasPages())
    <div class="mt-4">
        {{ $licenses->links() }}
    </div>
@endif
@endsection
