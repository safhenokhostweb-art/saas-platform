@extends('layouts.admin')
@section('title', 'نتیجه مولد قالب AI')

@section('content')
<div dir="rtl" class="max-w-4xl mx-auto space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">نتیجه تولید قالب AI</h1>
      <p class="text-sm text-gray-500 mt-1">Job #{{ $job->id }} — {{ $job->inputTypeLabel() }}</p>
    </div>
    <a href="{{ route('admin.theme-builder.ai-generator') }}"
       class="text-sm text-violet-600 hover:text-violet-800 font-medium flex items-center gap-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      مولد جدید
    </a>
  </div>

  @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-5 py-4 text-sm font-medium">
      {{ session('success') }}
    </div>
  @endif

  {{-- Status banner --}}
  <div class="rounded-2xl p-5 text-white flex items-center gap-4
    {{ $job->isComplete() ? 'bg-gradient-to-r from-emerald-500 to-teal-600' : 'bg-gradient-to-r from-red-500 to-rose-600' }}">
    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
      @if($job->isComplete())
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      @else
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      @endif
    </div>
    <div class="flex-1">
      <p class="font-bold text-lg">
        @if($job->isComplete()) قالب با موفقیت تولید شد
        @else خطا در تولید قالب
        @endif
      </p>
      <p class="text-white/80 text-sm mt-0.5">
        @if($job->isComplete())
          پک قالب «{{ $themePack?->name }}» آماده استفاده است
        @else
          {{ $job->error_message }}
        @endif
      </p>
    </div>
  </div>

  @if($job->isComplete() && $themePack)
    {{-- Pack summary --}}
    @php $summary = $themePack->contentSummary(); @endphp
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
      @foreach([
        ['هدرها', $summary['headers']],
        ['فوترها', $summary['footers']],
        ['صفحات', $summary['pages']],
        ['تمپلیت‌ها', $summary['templates']],
        ['استایل', $summary['global_styles'] ? '✓' : '—'],
        ['داده نمونه', $summary['demo_data'] ? '✓' : '—'],
      ] as [$label, $val])
      <div class="bg-white rounded-xl border border-gray-100 p-3 text-center shadow-sm">
        <p class="text-2xl font-bold text-violet-600">{{ $val }}</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $label }}</p>
      </div>
      @endforeach
    </div>

    {{-- Sections used --}}
    @if($job->selected_sections)
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-50">
        <h3 class="text-sm font-semibold text-gray-700">بخش‌های تولیدشده</h3>
      </div>
      <div class="p-5 flex flex-wrap gap-2">
        @foreach($job->selected_sections as $section)
          <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full
            {{ ($section['enabled'] ?? false) ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-400 line-through' }}">
            {{ $section['label'] ?? $section['key'] }}
            @if($section['enabled'] ?? false)
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
            @endif
          </span>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Pack details --}}
    @php $packData = $themePack->pack_data ?? []; @endphp
    @if(!empty($packData['global_styles']))
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-50">
        <h3 class="text-sm font-semibold text-gray-700">استایل جهانی</h3>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          @foreach(['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color'] as $colorKey)
            @if(!empty($packData['global_styles'][$colorKey]))
            <div class="text-center">
              <div class="w-12 h-12 rounded-xl mx-auto mb-1.5 border-2 border-white shadow-md"
                   style="background:{{ $packData['global_styles'][$colorKey] }}"></div>
              <p class="text-xs font-mono text-gray-500">{{ $packData['global_styles'][$colorKey] }}</p>
              <p class="text-xs text-gray-400">{{ Str::after($colorKey, '_color') ?: $colorKey }}</p>
            </div>
            @endif
          @endforeach
        </div>
        @if(!empty($packData['global_styles']['font_family']))
        <p class="text-xs text-gray-500 mt-3">فونت: <span class="font-semibold">{{ $packData['global_styles']['font_family'] }}</span></p>
        @endif
      </div>
    </div>
    @endif

    {{-- Actions --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <p class="text-sm font-semibold text-gray-700 mb-4">اقدامات</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="{{ route('admin.theme-builder.ai-generator.install', $job) }}"
           onclick="return confirm('این پک را به‌عنوان قالب نصب کنیم؟')"
           class="flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-700 text-white font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/>
          </svg>
          نصب در کانواس
        </a>
        <a href="{{ route('admin.theme-builder.ai-generator.export', $job) }}"
           class="flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          دانلود JSON پک
        </a>
        <a href="{{ route('admin.theme-packs.index') }}"
           class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-xl transition text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
          </svg>
          مشاهده پک‌های قالب
        </a>
      </div>
    </div>
  @endif

  {{-- Job meta --}}
  <div class="bg-gray-50 rounded-xl border border-gray-100 p-4">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
      <div><span class="text-gray-400">نوع ورودی:</span> <span class="font-medium text-gray-700">{{ $job->inputTypeLabel() }}</span></div>
      <div><span class="text-gray-400">وضعیت:</span>
        <span class="font-medium {{ $job->isComplete() ? 'text-emerald-600' : 'text-red-600' }}">{{ $job->status }}</span>
      </div>
      @if($job->input_source)
      <div class="col-span-2 truncate"><span class="text-gray-400">منبع:</span> <span class="font-mono text-gray-600">{{ $job->input_source }}</span></div>
      @endif
    </div>
  </div>

</div>
@endsection
