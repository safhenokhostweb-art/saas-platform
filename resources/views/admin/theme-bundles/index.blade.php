@extends('layouts.admin')

@section('title', 'باندل‌های قالب')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                باندل‌های قالب
            </h1>
            <p class="text-sm text-gray-500 mt-1">فروش مجموعه‌ای از قالب‌ها با تخفیف ویژه</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.theme-bundles.orders') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                سفارش‌ها
            </a>
            <a href="{{ route('admin.theme-bundles.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                باندل جدید
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Revenue stat --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm text-gray-500">درآمد کل از باندل‌ها</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totalRevenue) }} <span class="text-sm font-normal text-gray-500">ریال</span></p>
        </div>
    </div>

    {{-- Bundles grid --}}
    @if($bundles->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
            <div class="w-16 h-16 bg-violet-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <p class="text-gray-500">هنوز باندلی ایجاد نشده.</p>
            <a href="{{ route('admin.theme-bundles.create') }}"
               class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
                اولین باندل را بسازید
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($bundles as $bundle)
                @php
                    $revenue = \App\Models\ThemeBundleOrder::where('bundle_id', $bundle->id)
                        ->where('status', 'paid')->sum('amount_rial');
                    $salesCount = \App\Models\ThemeBundleOrder::where('bundle_id', $bundle->id)
                        ->where('status', 'paid')->count();
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md transition">
                    {{-- Thumbnail --}}
                    @if($bundle->thumbnail)
                        <div class="h-40 bg-gray-100 overflow-hidden relative">
                            <img src="{{ $bundle->thumbnail }}" alt="{{ $bundle->name }}"
                                 class="w-full h-full object-cover">
                            @if($bundle->is_featured)
                                <span class="absolute top-2 right-2 bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">ویژه</span>
                            @endif
                            @if(!$bundle->is_active)
                                <span class="absolute top-2 left-2 bg-gray-600 text-white text-xs px-2 py-0.5 rounded-full">غیرفعال</span>
                            @endif
                        </div>
                    @else
                        <div class="h-40 bg-gradient-to-br from-violet-100 to-indigo-100 flex items-center justify-center relative">
                            <svg class="w-12 h-12 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            @if($bundle->is_featured)
                                <span class="absolute top-2 right-2 bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">ویژه</span>
                            @endif
                            @if(!$bundle->is_active)
                                <span class="absolute top-2 left-2 bg-gray-600 text-white text-xs px-2 py-0.5 rounded-full">غیرفعال</span>
                            @endif
                        </div>
                    @endif

                    <div class="p-5 space-y-3">
                        <div>
                            <h3 class="font-bold text-gray-900">{{ $bundle->name }}</h3>
                            <p class="text-xs text-gray-400 font-mono">{{ $bundle->slug }}</p>
                        </div>

                        @if($bundle->description)
                            <p class="text-sm text-gray-500 line-clamp-2">{{ $bundle->description }}</p>
                        @endif

                        {{-- Themes in bundle --}}
                        <div class="flex flex-wrap gap-1">
                            @foreach($bundle->themes() as $theme)
                                <span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-full">{{ $theme->name }}</span>
                            @endforeach
                        </div>

                        {{-- Price + stats --}}
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                            <div>
                                <p class="text-lg font-bold text-violet-700">
                                    @if($bundle->price === 0)
                                        رایگان
                                    @else
                                        {{ number_format($bundle->price) }} ریال
                                    @endif
                                </p>
                            </div>
                            <div class="text-left text-xs text-gray-400 space-y-0.5">
                                <p>{{ $salesCount }} فروش</p>
                                <p>{{ number_format($revenue) }} ریال</p>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-2 pt-1">
                            <a href="{{ route('admin.theme-bundles.edit', $bundle) }}"
                               class="flex-1 text-center text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg font-medium">
                                ویرایش
                            </a>
                            <form action="{{ route('admin.theme-bundles.destroy', $bundle) }}" method="POST"
                                  onsubmit="return confirm('آیا این باندل حذف شود؟')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
