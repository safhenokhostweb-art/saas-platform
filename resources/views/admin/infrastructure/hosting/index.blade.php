@extends('layouts.admin')

@section('header', 'هاستینگ')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مدیریت هاستینگ</h1>
        <p class="text-sm text-gray-500">پروایدرها، هزینه، تجدید و منابع تخصیص‌یافته</p>
    </div>
    <a href="{{ route('admin.infrastructure.hosting.create') }}" class="btn btn-primary text-sm whitespace-nowrap">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن هاستینگ
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">نام / پروایدر</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">محیط</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">منابع</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">هزینه ماهانه</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">تجدید بعدی</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">وضعیت</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($hosting as $h)
            <tr class="hover:bg-gray-50/50 transition-colors {{ $h->days_until_renewal !== null && $h->days_until_renewal <= 7 && $h->days_until_renewal >= 0 ? 'bg-amber-50/30' : '' }}">
                <td class="py-3 px-4">
                    <div class="font-semibold text-gray-800">{{ $h->name }}</div>
                    <div class="text-xs text-gray-500">{{ $h->provider }}{{ $h->plan ? ' · ' . $h->plan : '' }}</div>
                    @if($h->server)<div class="text-xs text-gray-400">سرور: {{ $h->server->name }}</div>@endif
                </td>
                <td class="py-3 px-4">
                    @php $envColors = ['production' => 'bg-green-100 text-green-700', 'staging' => 'bg-yellow-100 text-yellow-700', 'development' => 'bg-blue-100 text-blue-700']; $envLabels = ['production' => 'پروداکشن', 'staging' => 'استیجینگ', 'development' => 'دولاپمنت']; @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $envColors[$h->environment] ?? 'bg-gray-100 text-gray-600' }}">{{ $envLabels[$h->environment] ?? $h->environment }}</span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-500">
                    @if($h->cpu_cores_allocated){{ \App\Helpers\NumberHelper::formatFa($h->cpu_cores_allocated) }} vCPU @endif
                    @if($h->ram_gb_allocated) · {{ \App\Helpers\NumberHelper::formatFa($h->ram_gb_allocated) }} GB @endif
                    @if($h->disk_gb_allocated) · {{ \App\Helpers\NumberHelper::formatFa($h->disk_gb_allocated) }} GB @endif
                    @if(!$h->cpu_cores_allocated && !$h->ram_gb_allocated)— @endif
                </td>
                <td class="py-3 px-4 text-sm font-semibold text-gray-700">
                    {{ $h->monthly_cost ? number_format($h->monthly_cost) . ' ' . $h->currency : '—' }}
                </td>
                <td class="py-3 px-4 text-xs">
                    @if($h->next_renewal_at)
                        @if($h->days_until_renewal !== null && $h->days_until_renewal < 0)
                            <span class="text-red-600 font-bold">گذشته</span>
                        @elseif($h->days_until_renewal !== null && $h->days_until_renewal <= 7)
                            <span class="text-amber-600 font-semibold">{{ \App\Helpers\NumberHelper::formatFa($h->days_until_renewal) }} روز</span>
                        @else
                            <span class="text-gray-500">{{ \App\Helpers\Jalali::formatFa($h->next_renewal_at, 'Y/m/d') }}</span>
                        @endif
                    @else—@endif
                </td>
                <td class="py-3 px-4">
                    @php $stC = ['active'=>'bg-green-100 text-green-700','suspended'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-600','pending'=>'bg-yellow-100 text-yellow-700']; $stL = ['active'=>'فعال','suspended'=>'معلق','cancelled'=>'لغو‌شده','pending'=>'در انتظار']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $stC[$h->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $stL[$h->status] ?? $h->status }}</span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.infrastructure.hosting.edit', $h) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">ویرایش</a>
                        <form method="POST" action="{{ route('admin.infrastructure.hosting.destroy', $h) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="py-12 text-center text-gray-400 text-sm">هنوز هاستینگی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $hosting->links() }}</div>

@endsection
