@extends('layouts.admin')

@section('title', 'تاریخچه شبیه‌سازی بازیابی')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">تاریخچه شبیه‌سازی بازیابی</h1>
            <p class="text-sm text-gray-500 mt-1">شبیه‌سازی‌ها غیرمخرب هستند — هیچ داده‌ای تغییر نمی‌کند</p>
        </div>
        <a href="{{ route('admin.disaster-recovery.center.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            بازگشت به مرکز DR
        </a>
    </div>

    @if(!($simulations instanceof \Illuminate\Pagination\LengthAwarePaginator))

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-amber-800 text-sm">
        جدول شبیه‌سازی هنوز ایجاد نشده. ابتدا migration را اجرا کنید:
        <code class="bg-amber-100 px-2 py-0.5 rounded text-xs">php artisan migrate</code>
    </div>

    @elseif($simulations->isEmpty())

    <div class="bg-white rounded-xl border border-gray-200 py-16 flex flex-col items-center gap-4">
        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-gray-500 text-sm">هیچ شبیه‌سازی‌ای هنوز انجام نشده است</p>
        <a href="{{ route('admin.disaster-recovery.center.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition">
            رفتن به مرکز DR برای اجرای شبیه‌سازی
        </a>
    </div>

    @else

    {{-- Stats summary --}}
    @php
        $items     = $simulations->getCollection();
        $passCount = $items->where('result', 'PASS')->count();
        $failCount = $items->where('result', 'FAIL')->count();
        $avgDur    = $items->whereNotNull('duration_seconds')->avg('duration_seconds');
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold text-gray-900">@numFa($simulations->total())</div>
            <div class="text-xs text-gray-500 mt-1">کل شبیه‌سازی‌ها</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $passCount }}</div>
            <div class="text-xs text-gray-500 mt-1">PASS (این صفحه)</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold {{ $failCount > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $failCount }}</div>
            <div class="text-xs text-gray-500 mt-1">FAIL (این صفحه)</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold text-indigo-600">
                {{ $avgDur ? round($avgDur) . 's' : '—' }}
            </div>
            <div class="text-xs text-gray-500 mt-1">میانگین مدت</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">لیست شبیه‌سازی‌ها</h2>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach($simulations as $sim)
            <div x-data="{ open: false }" class="transition">

                {{-- Main row --}}
                <div class="grid grid-cols-7 gap-0 hover:bg-gray-50 transition cursor-pointer px-4 py-3 text-sm"
                     x-on:click="open = !open">

                    {{-- Date --}}
                    <div class="col-span-2">
                        <div class="text-gray-800 font-medium">{{ $sim->created_at->diffForHumans() }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ \App\Helpers\Jalali::formatFa($sim->created_at) }}</div>
                    </div>

                    {{-- Operator --}}
                    <div class="col-span-1 flex items-center">
                        <span class="text-gray-700">{{ $sim->operator_name }}</span>
                    </div>

                    {{-- Result badge --}}
                    <div class="col-span-1 flex items-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold
                            {{ $sim->isPass() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            @if($sim->isPass())
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            @else
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            @endif
                            {{ $sim->result }}
                        </span>
                    </div>

                    {{-- Duration --}}
                    <div class="col-span-1 flex items-center text-gray-600">
                        {{ $sim->durationForHumans() }}
                    </div>

                    {{-- RPO --}}
                    <div class="col-span-1 flex items-center text-gray-600 text-xs">
                        {{ $sim->estimated_rpo ?? '—' }}
                    </div>

                    {{-- Restore time + expand toggle --}}
                    <div class="col-span-1 flex items-center justify-between gap-2">
                        <span class="text-gray-600 text-xs">{{ $sim->estimated_restore_time ?? '—' }}</span>
                        @if($sim->components)
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                        @endif
                    </div>
                </div>

                {{-- Expand: component breakdown --}}
                @if($sim->components)
                <div x-show="open" x-collapse class="bg-indigo-50 border-t border-indigo-100 px-4 py-4">
                    <p class="text-xs font-semibold text-indigo-700 mb-3">جزئیات مؤلفه‌ها در زمان شبیه‌سازی:</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
                        @foreach($sim->components as $key => $comp)
                        <div class="bg-white rounded-lg border border-indigo-100 px-3 py-2">
                            <div class="text-xs font-semibold text-gray-700 truncate">{{ $comp['label'] }}</div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs font-bold {{ $comp['score'] === $comp['max'] ? 'text-green-600' : ($comp['score'] > 0 ? 'text-amber-600' : 'text-red-600') }}">
                                    {{ $comp['score'] }}/{{ $comp['max'] }}
                                </span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold
                                    {{ in_array($comp['status'], ['OK']) ? 'bg-green-100 text-green-700' :
                                       (in_array($comp['status'], ['WARN', 'UNKNOWN']) ? 'bg-amber-100 text-amber-700' :
                                       'bg-red-100 text-red-700') }}">
                                    {{ $comp['status'] }}
                                </span>
                            </div>
                            <div class="text-[10px] text-gray-500 mt-1 truncate" title="{{ $comp['detail'] }}">
                                {{ $comp['detail'] }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @if($sim->notes)
                    <p class="text-xs text-indigo-600 mt-3 italic">{{ $sim->notes }}</p>
                    @endif
                </div>
                @endif

            </div>
            @endforeach
        </div>

        {{-- Header row (sticky above table) --}}
        @if(false) {{-- used for reference only, not rendered --}}
        <div class="hidden grid-cols-7 gap-0 px-4 py-2 text-xs font-medium text-gray-500 bg-gray-50 border-b border-gray-100">
            <div class="col-span-2">تاریخ</div>
            <div class="col-span-1">اپراتور</div>
            <div class="col-span-1">نتیجه</div>
            <div class="col-span-1">مدت</div>
            <div class="col-span-1">RPO تخمینی</div>
            <div class="col-span-1">زمان بازیابی</div>
        </div>
        @endif

        <div class="px-5 py-4 border-t border-gray-100">
            {{ $simulations->links() }}
        </div>
    </div>

    @endif

</div>
@endsection
