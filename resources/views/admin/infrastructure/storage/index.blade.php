@extends('layouts.admin')

@section('header', 'استوریج')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مدیریت استوریج</h1>
        <p class="text-sm text-gray-500">پروایدرها، ظرفیت، مصرف و رشد ماهانه</p>
    </div>
    <a href="{{ route('admin.infrastructure.storage.create') }}" class="btn btn-primary text-sm whitespace-nowrap">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        افزودن سرویس استوریج
    </a>
</div>

{{-- Summary --}}
@if($totalCapacityGb)
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-600">مصرف کل</span>
        <span class="text-sm text-gray-500">{{ number_format($totalUsedGb) }} / {{ number_format($totalCapacityGb) }} GB</span>
    </div>
    <div class="h-3 rounded-full bg-gray-100 overflow-hidden">
        @php $overallPercent = $totalCapacityGb ? round(($totalUsedGb / $totalCapacityGb) * 100) : 0; @endphp
        <div class="h-full rounded-full {{ $overallPercent >= 90 ? 'bg-red-500' : ($overallPercent >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
             style="width: {{ min($overallPercent, 100) }}%"></div>
    </div>
    <p class="text-xs text-gray-400 mt-1">{{ \App\Helpers\NumberHelper::formatFa($overallPercent) }}% استفاده‌شده</p>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">نام / درایور</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">هدف</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">ظرفیت / مصرف</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">رشد ماهانه</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">دیسک Laravel</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">وضعیت</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-600">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($providers as $p)
            @php $usagePct = $p->usage_percent; @endphp
            <tr class="hover:bg-gray-50/50 transition-colors {{ $usagePct !== null && $usagePct >= 90 ? 'bg-red-50/30' : '' }}">
                <td class="py-3 px-4">
                    <div class="font-semibold text-gray-800">{{ $p->name }}</div>
                    <div class="text-xs text-gray-400">{{ strtoupper($p->driver) }}{{ $p->endpoint ? ' · ' . parse_url($p->endpoint, PHP_URL_HOST) : '' }}</div>
                    @if($p->bucket)<div class="text-xs text-gray-400">Bucket: {{ $p->bucket }}</div>@endif
                </td>
                <td class="py-3 px-4">
                    @php $pColors = ['primary'=>'bg-indigo-50 text-indigo-700','backup'=>'bg-blue-50 text-blue-700','cdn'=>'bg-sky-50 text-sky-700','tenant-uploads'=>'bg-violet-50 text-violet-700','exports'=>'bg-amber-50 text-amber-700']; $pLabels = ['primary'=>'اصلی','backup'=>'بکاپ','cdn'=>'CDN','tenant-uploads'=>'آپلود تِننت','exports'=>'خروجی']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $pColors[$p->purpose] ?? 'bg-gray-100 text-gray-600' }}">{{ $pLabels[$p->purpose] ?? $p->purpose }}</span>
                </td>
                <td class="py-3 px-4">
                    @if($p->capacity_gb)
                    <div class="text-xs font-medium text-gray-700">{{ number_format($p->used_gb ?? 0) }} / {{ number_format($p->capacity_gb) }} GB</div>
                    @if($usagePct !== null)
                    <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden mt-1 w-24">
                        <div class="h-full rounded-full {{ $usagePct >= 90 ? 'bg-red-500' : ($usagePct >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                             style="width: {{ min($usagePct, 100) }}%"></div>
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $usagePct }}%</div>
                    @endif
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="py-3 px-4 text-xs text-gray-600">
                    {{ $p->monthly_growth_gb !== null ? (($p->monthly_growth_gb >= 0 ? '+' : '') . number_format($p->monthly_growth_gb) . ' GB') : '—' }}
                </td>
                <td class="py-3 px-4">
                    @if($p->disk_name)
                        <code class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $p->disk_name }}</code>
                    @else
                        <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="py-3 px-4">
                    @php $stC = ['active'=>'bg-green-100 text-green-700','degraded'=>'bg-yellow-100 text-yellow-700','full'=>'bg-red-100 text-red-700','inactive'=>'bg-gray-100 text-gray-600']; $stL = ['active'=>'فعال','degraded'=>'ضعیف','full'=>'پر','inactive'=>'غیرفعال']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $stC[$p->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $stL[$p->status] ?? $p->status }}</span>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.infrastructure.storage.edit', $p) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">ویرایش</a>
                        <form method="POST" action="{{ route('admin.infrastructure.storage.destroy', $p) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="py-12 text-center text-gray-400 text-sm">هنوز سرویس استوریجی ثبت نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
