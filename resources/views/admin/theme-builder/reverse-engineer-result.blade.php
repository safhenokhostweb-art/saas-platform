@extends('layouts.admin')

@section('title', 'نتیجه مهندسی معکوس')

@section('content')
@php
  $scores   = $job->similarity_scores ?? [];
  $dom      = $job->dom_structure ?? [];
  $layout   = $job->layout_map ?? [];
  $visual   = $job->visual_profile ?? [];
  $commerce = $job->commerce_profile ?? [];
  $quality  = $job->quality_report ?? [];
  $meta     = $job->meta ?? [];

  $bestMatch  = $scores['best_match_label'] ?? '—';
  $bestScore  = $scores['best_match_score'] ?? 0;
  $overall    = $scores['overall'] ?? 0;
  $structure  = $scores['structure'] ?? 0;
  $visual_s   = $scores['visual'] ?? 0;
  $commerce_s = $scores['commerce'] ?? 0;
  $layout_s   = $scores['layout'] ?? 0;

  $verdict       = $quality['verdict'] ?? 'UNKNOWN';
  $qualityScore  = $quality['score'] ?? 0;
  $findings      = $quality['findings'] ?? [];

  $verdictColor = match($verdict) {
    'PASS'    => 'emerald',
    'PARTIAL' => 'amber',
    'FAIL'    => 'red',
    default   => 'gray',
  };
  $verdictLabel = match($verdict) {
    'PASS'    => 'تایید شد',
    'PARTIAL' => 'نیمه‌تایید',
    'FAIL'    => 'رد شد',
    default   => 'نامشخص',
  };
@endphp
<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="{{ route('theme-builder.reverse-engineer') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </a>
          <h1 class="text-xl font-bold text-gray-900">نتیجه مهندسی معکوس بصری</h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <p class="text-sm text-gray-500">{{ $job->suggested_name }} — {{ $job->inputTypeLabel() }} — {{ $job->created_at->diffForHumans() }}</p>
          @if($job->isHeadless())
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-medium">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
              Headless (Playwright)
            </span>
          @else
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
              HTTP
            </span>
          @endif
        </div>
      </div>
      <div class="flex gap-2 shrink-0">
        @if($job->isComplete())
        <a href="{{ route('admin.theme-builder.reverse-engineer.export', $job) }}"
          class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-1.5 shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          دانلود JSON
        </a>
        <form action="{{ route('admin.theme-builder.reverse-engineer.install', $job) }}" method="POST">
          @csrf
          <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm rounded-xl hover:bg-violet-700 transition-colors flex items-center gap-1.5 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
            نصب در Canvas
          </button>
        </form>
        @endif
      </div>
    </div>

    {{-- Verdict Banner --}}
    <div class="bg-{{ $verdictColor }}-50 border border-{{ $verdictColor }}-200 rounded-2xl p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-{{ $verdictColor }}-100 flex items-center justify-center shrink-0">
        @if($verdict === 'PASS')
          <svg class="w-7 h-7 text-{{ $verdictColor }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @elseif($verdict === 'PARTIAL')
          <svg class="w-7 h-7 text-{{ $verdictColor }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        @else
          <svg class="w-7 h-7 text-{{ $verdictColor }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @endif
      </div>
      <div class="flex-1">
        <div class="flex items-center gap-3">
          <h2 class="text-lg font-bold text-{{ $verdictColor }}-800">{{ $verdictLabel }}</h2>
          <span class="text-2xl font-black text-{{ $verdictColor }}-700">{{ $qualityScore }}/100</span>
        </div>
        <p class="text-sm text-{{ $verdictColor }}-700 mt-0.5">{{ $quality['summary'] ?? '' }}</p>
      </div>
      @if($bestMatch !== '—')
      <div class="text-center shrink-0 bg-white rounded-xl px-4 py-2 border border-{{ $verdictColor }}-100">
        <p class="text-xs text-gray-500 mb-0.5">بیشترین شباهت</p>
        <p class="text-xl font-black text-violet-700">{{ $bestMatch }}</p>
        <p class="text-sm font-semibold text-gray-600">{{ $bestScore }}%</p>
      </div>
      @endif
    </div>

    {{-- Score Dashboard --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
      @foreach([
        ['کلی','overall',$overall,'violet'],
        ['ساختار','structure',$structure,'indigo'],
        ['بصری','visual',$visual_s,'blue'],
        ['تجاری','commerce',$commerce_s,'cyan'],
        ['چیدمان','layout',$layout_s,'teal'],
      ] as [$label, $key, $val, $color])
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center shadow-sm">
        <div class="text-2xl font-black {{ $val >= 70 ? 'text-'.$color.'-600' : ($val >= 40 ? 'text-amber-600' : 'text-red-500') }}">
          {{ $val }}<span class="text-sm font-normal text-gray-400">%</span>
        </div>
        <p class="text-xs font-medium text-gray-500 mt-1">{{ $label }}</p>
        <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
          <div class="h-full rounded-full bg-{{ $color }}-500 transition-all" style="width: {{ $val }}%"></div>
        </div>
      </div>
      @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      {{-- Left: Per-site scores --}}
      <div class="lg:col-span-2 space-y-6">

        {{-- Per-site similarity --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-800">امتیاز شباهت به هر سایت مرجع</h3>
          </div>
          <div class="p-4 space-y-3">
            @foreach(($scores['per_site'] ?? []) as $siteKey => $siteData)
            @php
              $siteOverall = $siteData['overall'] ?? 0;
              $isBest = $siteKey === ($scores['best_match'] ?? '');
              $barColor = $siteOverall >= 70 ? 'bg-emerald-500' : ($siteOverall >= 40 ? 'bg-amber-400' : 'bg-red-400');
            @endphp
            <div class="{{ $isBest ? 'bg-violet-50 border border-violet-200 rounded-xl p-3' : 'px-1' }}">
              <div class="flex items-center gap-3">
                <div class="w-24 shrink-0">
                  <span class="text-sm font-semibold {{ $isBest ? 'text-violet-700' : 'text-gray-700' }}">
                    {{ $siteData['label'] ?? $siteKey }}
                    @if($isBest) <span class="text-xs text-violet-500">✓ بهترین</span> @endif
                  </span>
                  <p class="text-xs text-gray-400">{{ $siteData['type'] ?? '' }}</p>
                </div>
                <div class="flex-1">
                  <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="{{ $barColor }} h-full rounded-full" style="width: {{ $siteOverall }}%"></div>
                  </div>
                </div>
                <span class="text-sm font-bold {{ $isBest ? 'text-violet-700' : 'text-gray-700' }} w-10 text-left">{{ $siteOverall }}%</span>
              </div>
              @if($isBest)
              <div class="grid grid-cols-4 gap-2 mt-2">
                @foreach(['structure' => 'ساختار', 'visual' => 'بصری', 'commerce' => 'تجاری', 'layout' => 'چیدمان'] as $k => $l)
                <div class="text-center">
                  <p class="text-xs font-bold text-violet-600">{{ $siteData[$k] ?? 0 }}%</p>
                  <p class="text-xs text-gray-400">{{ $l }}</p>
                </div>
                @endforeach
              </div>
              @endif
            </div>
            @endforeach
          </div>
        </div>

        {{-- Detected Sections --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-800">بخش‌های تشخیص‌داده‌شده</h3>
            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">{{ count($layout['sections'] ?? []) }} بخش</span>
          </div>
          <div class="p-4">
            @if(!empty($layout['sections']))
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              @foreach($layout['sections'] as $section)
              @php
                $sType = $section['type'] ?? '';
                $sCols = $section['columns'] ?? 1;
                $sectionIcons = [
                  'header'=>'🧭', 'hero'=>'🎯', 'features'=>'⭐', 'services'=>'🛠',
                  'pricing'=>'💰', 'faq'=>'❓', 'testimonials'=>'💬', 'blog'=>'📰',
                  'products'=>'🛒', 'cta'=>'📢', 'footer'=>'📌', 'stats'=>'📊',
                ];
                $icon = $sectionIcons[$sType] ?? '📦';
              @endphp
              <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
                <span class="text-base">{{ $icon }}</span>
                <div>
                  <p class="text-xs font-medium text-gray-700">{{ $sType }}</p>
                  <p class="text-xs text-gray-400">{{ $sCols }} ستون</p>
                </div>
              </div>
              @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400 text-center py-4">بخشی تشخیص داده نشد</p>
            @endif
          </div>
        </div>

        {{-- Commerce Profile --}}
        @if($commerce['is_commerce'] ?? false)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-800">الگوهای تجاری تشخیص‌داده‌شده</h3>
            <span class="text-xs bg-cyan-100 text-cyan-700 px-2 py-0.5 rounded-full">{{ $commerce['commerce_score'] ?? 0 }}/100</span>
          </div>
          <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
            @php
              $commerceItems = [
                ['کارت محصول', $commerce['product_cards']['detected'] ?? false, $commerce['product_cards']['layout'] ?? ''],
                ['بلاک قیمت', $commerce['price_blocks']['detected'] ?? false, $commerce['price_blocks']['format'] ?? ''],
                ['نشان تخفیف', $commerce['discount_badges']['detected'] ?? false, $commerce['discount_badges']['style'] ?? ''],
                ['گالری تصاویر', $commerce['gallery']['detected'] ?? false, $commerce['gallery']['type'] ?? ''],
                ['نظرات', $commerce['reviews']['detected'] ?? false, ''],
                ['مشخصات فنی', $commerce['specifications']['detected'] ?? false, $commerce['specifications']['style'] ?? ''],
              ];
            @endphp
            @foreach($commerceItems as [$label, $detected, $detail])
            <div class="flex items-center gap-2 rounded-lg border px-3 py-2 {{ $detected ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-100' }}">
              <div class="w-5 h-5 rounded-full flex items-center justify-center {{ $detected ? 'bg-emerald-500' : 'bg-gray-300' }}">
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  @if($detected)
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>
                  @endif
                </svg>
              </div>
              <div>
                <p class="text-xs font-medium {{ $detected ? 'text-emerald-700' : 'text-gray-500' }}">{{ $label }}</p>
                @if($detail)<p class="text-xs text-gray-400">{{ $detail }}</p>@endif
              </div>
            </div>
            @endforeach
          </div>
        </div>
        @endif
      </div>

      {{-- Right: Visual Profile + Quality --}}
      <div class="space-y-6">

        {{-- Visual Profile --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">پروفایل بصری</h3>
          </div>
          <div class="p-4 space-y-3">
            {{-- Color Palette --}}
            @php $palette = $visual['color_palette'] ?? []; @endphp
            @if(!empty($palette['all']))
            <div>
              <p class="text-xs text-gray-500 mb-1.5">پالت رنگی تشخیص‌داده‌شده</p>
              <div class="flex flex-wrap gap-1.5">
                @foreach(array_slice($palette['all'], 0, 10) as $hex)
                <div class="w-7 h-7 rounded-lg border border-white shadow-sm cursor-pointer" style="background: {{ $hex }}" title="{{ $hex }}"></div>
                @endforeach
              </div>
              <div class="grid grid-cols-3 gap-2 mt-2">
                <div class="text-center">
                  <div class="w-full h-8 rounded-lg border border-gray-100 shadow-sm" style="background: {{ $palette['primary'] ?? '#3b82f6' }}"></div>
                  <p class="text-xs text-gray-400 mt-1">اصلی</p>
                </div>
                <div class="text-center">
                  <div class="w-full h-8 rounded-lg border border-gray-100 shadow-sm" style="background: {{ $palette['secondary'] ?? '#8b5cf6' }}"></div>
                  <p class="text-xs text-gray-400 mt-1">ثانوی</p>
                </div>
                <div class="text-center">
                  <div class="w-full h-8 rounded-lg border border-gray-100 shadow-sm" style="background: {{ $palette['accent'] ?? '#f59e0b' }}"></div>
                  <p class="text-xs text-gray-400 mt-1">تاکیدی</p>
                </div>
              </div>
            </div>
            @endif

            {{-- Typography --}}
            @php $typo = $visual['typography'] ?? []; @endphp
            @if(!empty($typo['primary_font']))
            <div class="border-t border-gray-100 pt-3">
              <p class="text-xs text-gray-500 mb-2">تایپوگرافی</p>
              <div class="space-y-1">
                <div class="flex justify-between text-xs">
                  <span class="text-gray-400">فونت اصلی</span>
                  <span class="font-medium text-gray-700 font-mono">{{ $typo['primary_font'] }}</span>
                </div>
                <div class="flex justify-between text-xs">
                  <span class="text-gray-400">اندازه پایه</span>
                  <span class="font-medium text-gray-700">{{ $typo['base_size'] ?? '16px' }}</span>
                </div>
                <div class="flex justify-between text-xs">
                  <span class="text-gray-400">فاصله خطوط</span>
                  <span class="font-medium text-gray-700">{{ $typo['line_height'] ?? '1.6' }}</span>
                </div>
              </div>
            </div>
            @endif

            {{-- Other Visual Props --}}
            <div class="border-t border-gray-100 pt-3 space-y-1">
              @php $radius = $visual['border_radius'] ?? []; $shadows = $visual['shadow_system'] ?? []; @endphp
              <div class="flex justify-between text-xs">
                <span class="text-gray-400">گوشه‌گردانی</span>
                <span class="font-medium text-gray-700">{{ $radius['scale'] ?? '—' }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-400">سایه</span>
                <span class="font-medium text-gray-700">{{ $shadows['intensity'] ?? '—' }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-400">تراکم بصری</span>
                <span class="font-medium text-gray-700">{{ $visual['visual_density'] ?? '—' }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-400">انیمیشن</span>
                <span class="font-medium text-gray-700">{{ ($visual['has_animations'] ?? false) ? 'بله' : 'خیر' }}</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Quality Report --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-800">گزارش کیفی (فاز ۷)</h3>
            @if(count($findings) > 0)
            <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">{{ count($findings) }} یافته</span>
            @endif
          </div>
          <div class="p-4">
            @if(count($findings) > 0)
            <div class="space-y-2">
              @foreach($findings as $finding)
              @php
                $sev = $finding['severity'] ?? 'LOW';
                $sevColor = match($sev) { 'CRITICAL' => 'red', 'HIGH' => 'orange', 'MEDIUM' => 'amber', default => 'blue' };
              @endphp
              <div class="flex items-start gap-2 text-xs border border-{{ $sevColor }}-100 bg-{{ $sevColor }}-50 rounded-lg px-3 py-2">
                <span class="font-bold text-{{ $sevColor }}-700 shrink-0 mt-0.5">{{ $sev }}</span>
                <span class="text-{{ $sevColor }}-800">{{ $finding['message'] ?? '' }}</span>
              </div>
              @endforeach
            </div>
            @else
            <div class="text-center py-4">
              <svg class="w-8 h-8 text-emerald-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <p class="text-sm text-emerald-600 font-medium">بدون یافته — کیفیت کامل</p>
            </div>
            @endif
          </div>
        </div>

        {{-- ThemePack Info --}}
        @if($themePack)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">پک تولیدشده</h3>
          </div>
          <div class="p-4 space-y-2 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-400">نام</span>
              <span class="font-medium text-gray-700">{{ $themePack->name }}</span>
            </div>
            @php $pd = $themePack->pack_data ?? []; @endphp
            <div class="flex justify-between">
              <span class="text-gray-400">هدرها</span>
              <span class="font-medium text-gray-700">{{ count($pd['headers'] ?? []) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">فوترها</span>
              <span class="font-medium text-gray-700">{{ count($pd['footers'] ?? []) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">صفحات</span>
              <span class="font-medium text-gray-700">{{ count($pd['pages'] ?? []) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">تمپلیت‌ها</span>
              <span class="font-medium text-gray-700">{{ count($pd['templates'] ?? []) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">بلاک‌های صفحه اصلی</span>
              <span class="font-medium text-gray-700">{{ count($pd['pages'][0]['blocks'] ?? []) }}</span>
            </div>
          </div>
        </div>
        @endif

      </div>
    </div>

    {{-- Layout Map --}}
    @if(!empty($layout['relationships']['flow']))
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">نقشه جریان صفحه (Phase 2)</h3>
      <div class="flex flex-wrap items-center gap-2">
        @foreach($layout['relationships']['flow'] as $i => $sectionType)
        <div class="flex items-center gap-2">
          <div class="px-3 py-1.5 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-medium">
            {{ $sectionType }}
          </div>
          @if($i < count($layout['relationships']['flow']) - 1)
          <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
          @endif
        </div>
        @endforeach
      </div>
      <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-500">
        <div><span class="font-medium text-gray-700">چیدمان:</span> {{ $layout['layout_style'] ?? '—' }}</div>
        <div><span class="font-medium text-gray-700">کانتینر:</span> {{ $layout['container']['max_width'] ?? '—' }}</div>
        <div><span class="font-medium text-gray-700">فاصله‌گذاری:</span> {{ $layout['spacing_system']['system'] ?? '—' }}</div>
        <div><span class="font-medium text-gray-700">ریسپانسیو:</span> {{ ($layout['responsive']['is_mobile_first'] ?? false) ? 'Mobile-first' : 'Desktop-first' }}</div>
      </div>
    </div>
    @endif

    {{-- Headless Engine Detail Panel (only when extraction was via Playwright) --}}
    @if($job->isHeadless() && !empty($job->headless_data))
    @php
      $hd   = $job->headless_data;
      $hComp = $hd['components'] ?? [];
      $hFw  = $hd['framework'] ?? [];
    @endphp
    <div class="bg-white rounded-2xl border border-indigo-200 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-indigo-100 bg-indigo-50 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
          </svg>
        </div>
        <div>
          <h2 class="text-base font-bold text-indigo-900">نتایج موتور Headless (Playwright)</h2>
          <p class="text-xs text-indigo-600">داده‌های استخراج‌شده از مرورگر واقعی پس از اجرای JavaScript</p>
        </div>
        <div class="mr-auto flex items-center gap-2">
          @if($hFw['is_nextjs'] ?? false)
            <span class="px-2 py-0.5 rounded-full bg-black text-white text-xs font-medium">Next.js</span>
          @elseif($hFw['is_react'] ?? false)
            <span class="px-2 py-0.5 rounded-full bg-sky-500 text-white text-xs font-medium">React</span>
          @elseif($hFw['is_vue'] ?? false)
            <span class="px-2 py-0.5 rounded-full bg-green-500 text-white text-xs font-medium">Vue</span>
          @endif
          <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs">
            {{ number_format($hd['html_size'] ?? 0) }} bytes rendered
          </span>
        </div>
      </div>

      <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        {{-- Navigation --}}
        @if(!empty($hComp['nav']['items']))
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">ناوبری ({{ count($hComp['nav']['items']) }} آیتم)</p>
          <div class="flex flex-wrap gap-1">
            @foreach(array_slice($hComp['nav']['items'], 0, 12) as $navItem)
              <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs border border-indigo-100">{{ $navItem }}</span>
            @endforeach
          </div>
          <div class="flex gap-3 mt-2 text-xs text-gray-500">
            @if($hComp['nav']['is_sticky'] ?? false) <span class="text-emerald-600">✓ sticky</span> @endif
            @if($hComp['nav']['has_mega_menu'] ?? false) <span class="text-emerald-600">✓ mega menu</span> @endif
            @if($hComp['nav']['has_cart'] ?? false) <span class="text-emerald-600">✓ cart</span> @endif
            @if($hComp['nav']['has_search'] ?? false) <span class="text-emerald-600">✓ search</span> @endif
          </div>
        </div>
        @endif

        {{-- Hero --}}
        @if(!empty($hComp['hero']['headline']))
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Hero Section</p>
          <p class="text-sm font-medium text-gray-800 leading-snug mb-1">{{ Str::limit($hComp['hero']['headline'], 80) }}</p>
          @if(!empty($hComp['hero']['subheadline']))
            <p class="text-xs text-gray-500 mb-2">{{ Str::limit($hComp['hero']['subheadline'], 100) }}</p>
          @endif
          @if(!empty($hComp['hero']['cta_buttons']))
            <div class="flex flex-wrap gap-1">
              @foreach($hComp['hero']['cta_buttons'] as $btn)
                <span class="px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 text-xs border border-violet-100">{{ $btn }}</span>
              @endforeach
            </div>
          @endif
        </div>
        @endif

        {{-- Product Grid --}}
        @if(($hComp['product_grid']['count'] ?? 0) > 0)
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">گرید محصولات</p>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">تعداد کارت:</span>
              <span class="font-medium text-gray-800">{{ $hComp['product_grid']['count'] }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">ستون‌ها:</span>
              <span class="font-medium text-gray-800">{{ $hComp['product_grid']['columns'] ?? '؟' }}</span>
            </div>
            <div class="flex gap-2 mt-1">
              @if($hComp['product_grid']['has_price'] ?? false) <span class="text-xs text-emerald-600">✓ قیمت</span> @endif
              @if($hComp['product_grid']['has_image'] ?? false) <span class="text-xs text-emerald-600">✓ تصویر</span> @endif
              @if($hComp['product_grid']['has_badge'] ?? false) <span class="text-xs text-emerald-600">✓ بج</span> @endif
              @if($hComp['product_grid']['has_rating'] ?? false) <span class="text-xs text-emerald-600">✓ امتیاز</span> @endif
            </div>
          </div>
        </div>
        @endif

        {{-- Colors --}}
        @if(!empty($hComp['colors']))
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">رنگ‌های computed (مرورگر)</p>
          <div class="flex flex-wrap gap-1.5">
            @foreach(array_slice($hComp['colors'], 0, 10) as $hex)
              <div class="flex items-center gap-1">
                <div class="w-5 h-5 rounded border border-gray-200 shadow-sm" style="background-color: {{ $hex }}"></div>
                <span class="text-xs text-gray-500 font-mono">{{ $hex }}</span>
              </div>
            @endforeach
          </div>
        </div>
        @endif

        {{-- Typography --}}
        @if(!empty($hComp['typography']['fonts']))
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">فونت‌ها (computed)</p>
          <div class="space-y-1">
            @foreach($hComp['typography']['fonts'] as $font)
              <span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-mono">{{ $font }}</span>
            @endforeach
          </div>
          @if(!empty($hComp['typography']['base_size']))
            <p class="text-xs text-gray-400 mt-1">base size: {{ $hComp['typography']['base_size'] }}</p>
          @endif
        </div>
        @endif

        {{-- Sliders & Filters --}}
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">کامپوننت‌های شناسایی‌شده</p>
          <div class="space-y-1.5">
            @if(!empty($hComp['sliders']))
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-xs text-gray-700">اسلایدر: {{ implode(', ', $hComp['sliders']) }}</span>
              </div>
            @endif
            @if($hComp['search']['found'] ?? false)
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-xs text-gray-700">جستجو: {{ $hComp['search']['placeholder'] ?: 'فعال' }}</span>
              </div>
            @endif
            @if($hComp['filters']['found'] ?? false)
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-xs text-gray-700">فیلتر: {{ count($hComp['filters']['items'] ?? []) }} آیتم</span>
              </div>
            @endif
            @if($hComp['mega_menu']['detected'] ?? false)
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-xs text-gray-700">Mega Menu: {{ $hComp['mega_menu']['columns'] ?? '؟' }} ستون</span>
              </div>
            @endif
            @if($hd['had_screenshot'] ?? false)
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span>
                <span class="text-xs text-gray-700">اسکرین‌شات ذخیره‌شده</span>
              </div>
            @endif
          </div>
        </div>

      </div>
    </div>
    @endif

    {{-- Screenshot Preview (Playwright only) --}}
    @if($job->isHeadless() && !empty($meta['headless_screenshot']))
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <div>
          <h3 class="text-sm font-semibold text-gray-800">اسکرین‌شات Playwright</h3>
          <p class="text-xs text-gray-400">رندر واقعی مرورگر — device: {{ $meta['device'] ?? 'desktop' }}</p>
        </div>
        <a href="{{ Storage::url($meta['headless_screenshot']) }}" target="_blank"
           class="mr-auto text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          باز کن
        </a>
      </div>
      <div class="p-4 bg-gray-50">
        <img src="{{ Storage::url($meta['headless_screenshot']) }}"
             alt="Playwright screenshot"
             class="w-full max-h-96 object-contain object-top rounded-lg border border-gray-200 shadow-sm bg-white"
             loading="lazy">
      </div>
    </div>
    @endif

    {{-- Performance Metrics (Playwright only) --}}
    @if($job->isHeadless() && !empty($meta['playwright']['performance']))
    @php
      $perf = $meta['playwright']['performance'];
      $playwrightMeta = $meta['playwright'] ?? [];
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
        </div>
        <h3 class="text-sm font-semibold text-gray-800">متریک‌های عملکرد (Performance)</h3>
      </div>
      <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
        @php
          $navTime  = $perf['navigation_time_ms']  ?? null;
          $fcp      = $perf['fcp_ms']               ?? null;
          $lcp      = $perf['lcp_ms']               ?? null;
          $jsBundles = $perf['js_bundle_count']     ?? null;
          $cssCount = $playwrightMeta['css_vars_count'] ?? null;
        @endphp
        @if($navTime !== null)
        <div class="bg-gray-50 rounded-xl p-3 text-center">
          <div class="text-lg font-bold text-gray-800">{{ number_format($navTime) }}</div>
          <div class="text-xs text-gray-400 mt-0.5">Navigation (ms)</div>
          <div class="mt-1 text-xs {{ $navTime < 3000 ? 'text-emerald-600' : ($navTime < 6000 ? 'text-amber-600' : 'text-red-500') }}">
            {{ $navTime < 3000 ? 'سریع' : ($navTime < 6000 ? 'متوسط' : 'کند') }}
          </div>
        </div>
        @endif
        @if($fcp !== null)
        <div class="bg-gray-50 rounded-xl p-3 text-center">
          <div class="text-lg font-bold text-gray-800">{{ number_format($fcp) }}</div>
          <div class="text-xs text-gray-400 mt-0.5">FCP (ms)</div>
          <div class="mt-1 text-xs {{ $fcp < 1800 ? 'text-emerald-600' : ($fcp < 3000 ? 'text-amber-600' : 'text-red-500') }}">
            {{ $fcp < 1800 ? 'خوب' : ($fcp < 3000 ? 'بهبود لازم' : 'ضعیف') }}
          </div>
        </div>
        @endif
        @if($jsBundles !== null)
        <div class="bg-gray-50 rounded-xl p-3 text-center">
          <div class="text-lg font-bold text-gray-800">{{ $jsBundles }}</div>
          <div class="text-xs text-gray-400 mt-0.5">JS Bundles</div>
          <div class="mt-1 text-xs {{ $jsBundles <= 5 ? 'text-emerald-600' : ($jsBundles <= 15 ? 'text-amber-600' : 'text-red-500') }}">
            {{ $jsBundles <= 5 ? 'بهینه' : ($jsBundles <= 15 ? 'متوسط' : 'زیاد') }}
          </div>
        </div>
        @endif
        @if($cssCount !== null)
        <div class="bg-gray-50 rounded-xl p-3 text-center">
          <div class="text-lg font-bold text-gray-800">{{ $cssCount }}</div>
          <div class="text-xs text-gray-400 mt-0.5">CSS Variables</div>
          <div class="mt-1 text-xs {{ $cssCount >= 10 ? 'text-emerald-600' : 'text-gray-400' }}">
            {{ $cssCount >= 10 ? 'Design System' : 'محدود' }}
          </div>
        </div>
        @endif
      </div>
      @if(!empty($playwrightMeta['framework']))
      @php $fw = $playwrightMeta['framework']; @endphp
      <div class="px-5 pb-4 flex flex-wrap gap-2">
        @if($fw['is_nextjs'] ?? false) <span class="px-2 py-0.5 rounded-full bg-black text-white text-xs font-medium">Next.js</span> @endif
        @if($fw['is_react'] ?? false) <span class="px-2 py-0.5 rounded-full bg-sky-500 text-white text-xs font-medium">React</span> @endif
        @if($fw['is_vue'] ?? false) <span class="px-2 py-0.5 rounded-full bg-green-500 text-white text-xs font-medium">Vue</span> @endif
        @if($fw['is_angular'] ?? false) <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-xs font-medium">Angular</span> @endif
        @if($fw['is_nuxt'] ?? false) <span class="px-2 py-0.5 rounded-full bg-green-600 text-white text-xs font-medium">Nuxt</span> @endif
        @if($fw['is_tailwind'] ?? false) <span class="px-2 py-0.5 rounded-full bg-cyan-500 text-white text-xs font-medium">Tailwind</span> @endif
        @if($fw['is_bootstrap'] ?? false) <span class="px-2 py-0.5 rounded-full bg-purple-500 text-white text-xs font-medium">Bootstrap</span> @endif
        @if($fw['is_mui'] ?? false) <span class="px-2 py-0.5 rounded-full bg-blue-500 text-white text-xs font-medium">MUI</span> @endif
      </div>
      @endif
    </div>
    @endif

    {{-- Snapshots Explorer (Playwright only) --}}
    @if($job->isHeadless() && !empty($meta['snapshots']))
    @php
      $snap     = $meta['snapshots'];
      $cssSnap  = $snap['css']    ?? [];
      $domSnap  = $snap['dom']    ?? [];
      $laySnap  = $snap['layout'] ?? [];
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
          </svg>
        </div>
        <h3 class="text-sm font-semibold text-gray-800">Snapshots Explorer</h3>
        <p class="text-xs text-gray-400">داده‌های استخراج‌شده برای موتور مشابهت</p>
      </div>
      <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-5">

        {{-- CSS Snapshot --}}
        <div class="space-y-3">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">CSS Snapshot</p>
          @if(!empty($cssSnap['primary_color']))
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg border border-gray-200 shadow-sm" style="background-color: {{ $cssSnap['primary_color'] }}"></div>
            <div>
              <div class="text-xs font-medium text-gray-700 font-mono">{{ $cssSnap['primary_color'] }}</div>
              <div class="text-xs text-gray-400">رنگ اصلی (primary)</div>
            </div>
          </div>
          @endif
          @if(!empty($cssSnap['color_scheme']))
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full {{ $cssSnap['color_scheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-300' }}"></span>
            <span class="text-xs text-gray-600">{{ $cssSnap['color_scheme'] === 'dark' ? 'Dark Mode' : 'Light Mode' }}</span>
          </div>
          @endif
          @if(!empty($cssSnap['fonts']))
          <div>
            <p class="text-xs text-gray-400 mb-1">فونت‌ها از CSS vars:</p>
            <div class="flex flex-wrap gap-1">
              @foreach($cssSnap['fonts'] as $font)
                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-mono">{{ $font }}</span>
              @endforeach
            </div>
          </div>
          @endif
          @if(!empty($cssSnap['border_radius']))
          <div class="text-xs text-gray-600">
            <span class="text-gray-400">border-radius:</span>
            <span class="font-mono font-medium">{{ $cssSnap['border_radius'] }}</span>
          </div>
          @endif
          @if($cssSnap['has_animations'] ?? false)
          <div class="flex items-center gap-1 text-xs text-emerald-600">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            CSS Animations فعال
          </div>
          @endif
          @if(!empty($cssSnap['style_framework']))
          <div class="text-xs text-indigo-600 font-medium">Framework: {{ $cssSnap['style_framework'] }}</div>
          @endif
        </div>

        {{-- DOM Snapshot --}}
        <div class="space-y-3">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">DOM Snapshot</p>
          @if(isset($domSnap['section_count']))
          <div class="text-xs text-gray-600">
            <span class="text-gray-400">بخش‌های شناسایی‌شده:</span>
            <span class="font-semibold text-gray-800 mr-1">{{ $domSnap['section_count'] }}</span>
          </div>
          @endif
          @if(isset($domSnap['product_count']))
          <div class="text-xs text-gray-600">
            <span class="text-gray-400">محصولات:</span>
            <span class="font-semibold {{ $domSnap['product_count'] > 0 ? 'text-emerald-700' : 'text-gray-400' }} mr-1">{{ $domSnap['product_count'] }}</span>
          </div>
          @endif
          @if(!empty($domSnap['sections_detected']))
          <div>
            <p class="text-xs text-gray-400 mb-1">بخش‌ها:</p>
            <div class="flex flex-wrap gap-1">
              @foreach($domSnap['sections_detected'] as $sec)
                <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs border border-indigo-100">{{ $sec }}</span>
              @endforeach
            </div>
          </div>
          @endif
          @if($domSnap['has_header'] ?? false) <div class="text-xs text-emerald-600">✓ هدر</div> @endif
          @if($domSnap['has_hero'] ?? false) <div class="text-xs text-emerald-600">✓ Hero</div> @endif
          @if($domSnap['has_footer'] ?? false) <div class="text-xs text-emerald-600">✓ فوتر</div> @endif
          @if($domSnap['is_rtl'] ?? false) <div class="text-xs text-blue-600">✓ RTL (فارسی/عربی)</div> @endif
          @if(!empty($domSnap['framework']))
          <div class="text-xs font-medium text-gray-600">Framework: {{ $domSnap['framework'] }}</div>
          @endif
        </div>

        {{-- Layout Snapshot --}}
        <div class="space-y-3">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Layout Snapshot</p>
          @if(!empty($laySnap['column_signature']))
          <div class="text-xs text-gray-600">
            <span class="text-gray-400">امضای ستون:</span>
            <span class="font-mono font-semibold text-gray-800 mr-1">{{ $laySnap['column_signature'] }}</span>
          </div>
          @endif
          @if(!empty($laySnap['layout_style']))
          <div class="text-xs text-gray-600">
            <span class="text-gray-400">استایل:</span>
            <span class="font-medium text-gray-800 mr-1">{{ $laySnap['layout_style'] }}</span>
          </div>
          @endif
          @if(isset($laySnap['density']))
          <div class="text-xs text-gray-600">
            <span class="text-gray-400">تراکم:</span>
            <span class="font-medium text-gray-800 mr-1">{{ $laySnap['density'] }}</span>
          </div>
          @endif
          @if(!empty($laySnap['section_grid_map']))
          <div>
            <p class="text-xs text-gray-400 mb-1">نقشه گرید:</p>
            <div class="space-y-0.5">
              @foreach($laySnap['section_grid_map'] as $secMap)
              <div class="flex items-center gap-1.5 text-xs text-gray-600">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                <span class="text-gray-500">{{ $secMap['type'] ?? '?' }}:</span>
                <span class="font-mono">{{ $secMap['columns'] ?? '?' }} col</span>
              </div>
              @endforeach
            </div>
          </div>
          @endif
        </div>

      </div>
    </div>
    @endif

  </div>
</div>
@endsection
