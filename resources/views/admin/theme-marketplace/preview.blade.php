@extends('layouts.admin')

@section('title', 'پیش‌نمایش: ' . $theme->name)

@section('content')
<div class="space-y-6" dir="rtl">

  {{-- Breadcrumb --}}
  <div class="flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('admin.theme-marketplace.index') }}" class="hover:text-indigo-600">مارکت‌پلیس</a>
    <svg class="w-3 h-3 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-800 font-medium">{{ $theme->name }}</span>
  </div>

  {{-- Header --}}
  <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="bg-linear-to-l from-indigo-50 to-violet-50 px-8 py-6 border-b border-gray-100">
      <div class="flex items-start gap-6">
        {{-- Thumbnail --}}
        <div class="w-24 h-24 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shrink-0 shadow-sm">
          @if($theme->thumbnail)
            <img src="{{ $theme->thumbnail }}" class="w-full h-full object-cover" alt="{{ $theme->name }}">
          @else
            <div class="w-full h-full flex items-center justify-center bg-linear-to-br from-indigo-100 to-violet-100">
              <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </div>
          @endif
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <h1 class="text-2xl font-bold text-gray-900">{{ $theme->name }}</h1>
              <p class="text-sm text-gray-400 mt-0.5 font-mono">{{ $theme->slug }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              @if($meta?->is_bestseller)
                <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs px-3 py-1 rounded-full font-semibold">🔥 پرفروش</span>
              @endif
              @if($meta?->is_new_release)
                <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-semibold">✨ جدید</span>
              @endif
              @if($meta?->is_featured)
                <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs px-3 py-1 rounded-full font-semibold">⭐ ویژه</span>
              @endif
              <span class="bg-{{ $theme->status === 'published' ? 'emerald' : 'gray' }}-100 text-{{ $theme->status === 'published' ? 'emerald' : 'gray' }}-700 text-xs px-3 py-1 rounded-full font-semibold">
                {{ $theme->status === 'published' ? 'منتشر' : 'پیش‌نویس' }}
              </span>
            </div>
          </div>
          <div class="flex flex-wrap gap-3 mt-3 text-sm text-gray-600">
            @if($theme->category)
              <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                {{ \App\Models\Theme::categoryLabel($theme->category) }}
              </span>
            @endif
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              {{ number_format($theme->installs_count ?? 0) }} نصب
            </span>
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
              {{ number_format($meta?->sales_count ?? 0) }} فروش
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Pricing cards --}}
    <div class="px-8 py-6 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">قیمت‌گذاری</h2>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- One-time --}}
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
          <p class="text-xs text-gray-500 mb-1">یک‌بار خرید</p>
          @if($theme->marketplace_price > 0)
            <p class="text-xl font-bold text-gray-900">{{ number_format($theme->marketplace_price) }}</p>
            <p class="text-xs text-gray-400">ریال</p>
          @else
            <p class="text-xl font-bold text-emerald-600">رایگان</p>
          @endif
        </div>
        {{-- Annual --}}
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-center {{ $meta?->annual_price ? '' : 'opacity-40' }}">
          <p class="text-xs text-indigo-600 mb-1">سالانه</p>
          @if($meta?->annual_price)
            <p class="text-xl font-bold text-indigo-700">{{ number_format($meta->annual_price) }}</p>
            <p class="text-xs text-indigo-400">ریال / سال</p>
          @else
            <p class="text-sm text-gray-400">تنظیم نشده</p>
          @endif
        </div>
        {{-- Lifetime --}}
        <div class="bg-violet-50 border border-violet-200 rounded-xl p-4 text-center {{ $meta?->lifetime_price ? '' : 'opacity-40' }}">
          <p class="text-xs text-violet-600 mb-1">مادام‌العمر</p>
          @if($meta?->lifetime_price)
            <p class="text-xl font-bold text-violet-700">{{ number_format($meta->lifetime_price) }}</p>
            <p class="text-xs text-violet-400">ریال</p>
          @else
            <p class="text-sm text-gray-400">تنظیم نشده</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Quality scores --}}
    @if($meta && $meta->design_score)
    <div class="px-8 py-6 border-b border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-700">امتیاز کیفیت</h2>
        @php
          $overall = $meta->getOverallScore();
          $badge   = $meta->getScoreBadge();
          $badgeColors = ['excellent'=>'emerald','good'=>'green','fair'=>'amber','needs-work'=>'red'];
          $badgeLabels = ['excellent'=>'عالی','good'=>'خوب','fair'=>'متوسط','needs-work'=>'نیاز به بهبود'];
          $bc = $badgeColors[$badge] ?? 'gray';
        @endphp
        <span class="inline-flex items-center gap-1.5 bg-{{ $bc }}-100 text-{{ $bc }}-700 text-sm font-bold px-3 py-1 rounded-full">
          {{ $overall }}/100 — {{ $badgeLabels[$badge] }}
        </span>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
          ['طراحی','design_score','indigo'],
          ['تجارت','commerce_score','emerald'],
          ['عملکرد','performance_score','sky'],
          ['کامل بودن','completeness_score','violet'],
          ['بازار','marketplace_score','amber'],
        ] as [$label, $field, $color])
        <div class="bg-{{ $color }}-50 border border-{{ $color }}-100 rounded-xl p-3 text-center">
          <p class="text-2xl font-bold text-{{ $color }}-700">{{ $meta->$field ?? 0 }}</p>
          <p class="text-xs text-{{ $color }}-500 mt-0.5">{{ $label }}</p>
          <div class="mt-2 h-1.5 bg-{{ $color }}-100 rounded-full overflow-hidden">
            <div class="h-full bg-{{ $color }}-400 rounded-full" style="width: {{ $meta->$field ?? 0 }}%"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Description --}}
    @if($meta?->marketplace_description)
    <div class="px-8 py-6 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700 mb-3">توضیحات</h2>
      <p class="text-sm text-gray-600 leading-7 whitespace-pre-line">{{ $meta->marketplace_description }}</p>
    </div>
    @endif

    {{-- Screenshots --}}
    @if($meta?->screenshots && count($meta->screenshots) > 0)
    <div class="px-8 py-6 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">تصاویر ({{ count($meta->screenshots) }})</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        @foreach($meta->screenshots as $shot)
          <a href="{{ $shot }}" target="_blank" class="block">
            <img src="{{ $shot }}" alt="" class="w-full h-40 object-cover rounded-xl border border-gray-200 hover:opacity-90 transition">
          </a>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Requirements --}}
    @if($meta?->requirements && count($meta->requirements) > 0)
    <div class="px-8 py-6 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700 mb-3">ماژول‌های مورد نیاز</h2>
      <div class="flex flex-wrap gap-2">
        @foreach($meta->requirements as $req)
          <span class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-mono">{{ $req }}</span>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Preview URL + Version Notes --}}
    <div class="px-8 py-6 border-b border-gray-100">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @if($meta?->version_notes)
        <div>
          <h2 class="text-sm font-semibold text-gray-700 mb-2">یادداشت نسخه</h2>
          <p class="text-sm text-gray-600">{{ $meta->version_notes }}</p>
        </div>
        @endif
        @if($meta?->preview_url)
        <div>
          <h2 class="text-sm font-semibold text-gray-700 mb-2">پیش‌نمایش زنده</h2>
          <a href="{{ $meta->preview_url }}" target="_blank"
             class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            مشاهده دمو
          </a>
        </div>
        @endif
      </div>
    </div>

    {{-- Quality Issues --}}
    @if($meta?->quality_issues && count($meta->quality_issues) > 0)
    <div class="px-8 py-6 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700 mb-3">مشکلات کیفی شناسایی‌شده</h2>
      <ul class="space-y-2">
        @foreach($meta->quality_issues as $issue)
          <li class="flex items-start gap-2 text-sm text-amber-700">
            <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            {{ $issue }}
          </li>
        @endforeach
      </ul>
    </div>
    @endif

    {{-- Footer actions --}}
    <div class="px-8 py-5 bg-gray-50 flex items-center gap-3 flex-wrap">
      <a href="{{ route('admin.theme-marketplace.index') }}"
         class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        بازگشت
      </a>
      <a href="{{ route('admin.theme-marketplace.scoring') }}"
         class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        امتیازدهی
      </a>
      @if($meta?->preview_url)
      <a href="{{ $meta->preview_url }}" target="_blank"
         class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        مشاهده دمو
      </a>
      @endif
    </div>
  </div>

</div>
@endsection
