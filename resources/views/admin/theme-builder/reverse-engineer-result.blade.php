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
        <p class="text-sm text-gray-500">{{ $job->suggested_name }} — {{ $job->inputTypeLabel() }} — {{ $job->created_at->diffForHumans() }}</p>
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

  </div>
</div>
@endsection
