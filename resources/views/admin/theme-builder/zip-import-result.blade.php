@extends('layouts.admin')
@section('title', 'نتیجه ورود از ZIP — ' . $pack->name)

@section('content')
@php
  $result      = $importResult ?: [];
  $similarity  = $result['similarity'] ?? [];
  $quality     = $result['quality']    ?? [];
  $stats       = $result['stats']      ?? [];
  $phases      = $result['phases']     ?? [];
  $simScore    = $similarity['score']  ?? 0;
  $simGrade    = $similarity['grade']  ?? 'N/A';
  $qualScore   = $quality['score']     ?? 0;
  $qualGrade   = $quality['grade']     ?? 'N/A';

  $gradeColor = fn($g) => match($g) {
    'EXCELLENT' => 'text-green-700 bg-green-100',
    'PASS'      => 'text-blue-700 bg-blue-100',
    'PARTIAL'   => 'text-amber-700 bg-amber-100',
    default     => 'text-red-700 bg-red-100',
  };
  $progressColor = fn($s) => $s >= 90 ? 'bg-green-500' : ($s >= 70 ? 'bg-blue-500' : ($s >= 50 ? 'bg-amber-500' : 'bg-red-400'));
@endphp
<div class="min-h-screen bg-gray-50" dir="rtl">
  <div class="max-w-5xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="mb-8">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl {{ $simScore >= 90 ? 'bg-green-600' : ($simScore >= 70 ? 'bg-blue-600' : 'bg-amber-500') }} flex items-center justify-center shadow">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900" style="font-family: Vazirmatn, sans-serif;">{{ $pack->name }}</h1>
          <p class="text-sm text-gray-500">ورود از ZIP · {{ now()->format('Y/m/d') }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.theme-builder.index') }}" class="hover:text-indigo-600">ویرایشگر قالب</a>
        <span>›</span>
        <a href="{{ route('admin.theme-builder.zip-import.form') }}" class="hover:text-indigo-600">ورود از ZIP</a>
        <span>›</span>
        <span class="text-gray-900">نتیجه</span>
      </div>
    </div>

    {{-- Score Cards Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      {{-- Similarity Score --}}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">امتیاز شباهت ZIP</p>
        <div class="flex items-end gap-2 mb-2">
          <span class="text-4xl font-bold text-gray-900">{{ $simScore }}</span>
          <span class="text-gray-400 mb-1">/100</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
          <div class="{{ $progressColor($simScore) }} h-2 rounded-full transition-all" style="width: {{ $simScore }}%"></div>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $gradeColor($simGrade) }}">{{ $simGrade }}</span>
        @if($simScore >= 90)
          <p class="text-xs text-green-600 mt-1">✅ هدف ۹۰٪+ حاصل شد</p>
        @elseif($simScore >= 70)
          <p class="text-xs text-blue-600 mt-1">هدف نزدیک است</p>
        @else
          <p class="text-xs text-amber-600 mt-1">نیاز به بهبود منبع</p>
        @endif
      </div>

      {{-- Quality Score --}}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">کیفیت ThemePack</p>
        <div class="flex items-end gap-2 mb-2">
          <span class="text-4xl font-bold text-gray-900">{{ $qualScore }}</span>
          <span class="text-gray-400 mb-1">/100</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
          <div class="{{ $progressColor($qualScore) }} h-2 rounded-full transition-all" style="width: {{ $qualScore }}%"></div>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $gradeColor($qualGrade) }}">{{ $qualGrade }}</span>
      </div>

      {{-- ZIP Contents --}}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">محتویات ZIP</p>
        <div class="space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">فایل JSON</span>
            <span class="font-semibold">{{ $result['json_count'] ?? 0 }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">اسکرین‌شات</span>
            <span class="font-semibold">{{ $result['screenshot_count'] ?? 0 }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">فایل‌های asset</span>
            <span class="font-semibold">{{ $result['asset_count'] ?? 0 }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">کل فایل‌ها</span>
            <span class="font-semibold">{{ $result['total_files'] ?? 0 }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">زمان پردازش</span>
            <span class="font-semibold">{{ num_fa(($result['duration_ms'] ?? 0) / 1000, 1) }}s</span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {{-- Left Column: Details --}}
      <div class="lg:col-span-2 space-y-5">

        {{-- Source Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-4" style="font-family: Vazirmatn, sans-serif;">اطلاعات منبع</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-gray-500 mb-1">منبع تجاری</p>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                {{ $result['origin_label'] ?? 'ناشناخته' }}
              </span>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">فرمت JSON</p>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                {{ $result['source_label'] ?? 'Custom' }}
              </span>
            </div>
            @if (!empty($result['metadata']))
              @foreach(['name'=>'نام','version'=>'نسخه','author'=>'نویسنده'] as $key => $label)
                @if (!empty($result['metadata'][$key]))
                  <div>
                    <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
                    <p class="text-sm font-medium text-gray-900">{{ $result['metadata'][$key] }}</p>
                  </div>
                @endif
              @endforeach
            @endif
          </div>
        </div>

        {{-- ThemePack Stats --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-4" style="font-family: Vazirmatn, sans-serif;">آمار ThemePack</h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-2xl font-bold text-indigo-700">{{ $stats['page_count'] ?? 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">صفحه</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-2xl font-bold text-purple-700">{{ $stats['total_blocks'] ?? 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">بلوک</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-2xl font-bold text-blue-700">{{ $stats['block_type_variety'] ?? 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">نوع بلوک</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-2xl font-bold {{ ($stats['has_header'] ?? false) ? 'text-green-700' : 'text-red-400' }}">
                {{ ($stats['has_header'] ?? false) ? '✓' : '✗' }}
              </p>
              <p class="text-xs text-gray-500 mt-1">هدر/فوتر</p>
            </div>
          </div>
        </div>

        {{-- Similarity Breakdown --}}
        @if (!empty($similarity['breakdown']))
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-4" style="font-family: Vazirmatn, sans-serif;">تفکیک امتیاز شباهت</h3>
          <div class="space-y-3">
            @php
              $breakdownLabels = [
                'json_quality'          => ['label' => 'کیفیت JSON', 'max' => 40, 'color' => 'bg-indigo-500'],
                'format_confidence'     => ['label' => 'اطمینان فرمت', 'max' => 20, 'color' => 'bg-purple-500'],
                'screenshot_coverage'   => ['label' => 'پوشش اسکرین‌شات', 'max' => 20, 'color' => 'bg-pink-500'],
                'metadata_completeness' => ['label' => 'کامل بودن متادیتا', 'max' => 10, 'color' => 'bg-blue-500'],
                'asset_richness'        => ['label' => 'غنای asset', 'max' => 10, 'color' => 'bg-teal-500'],
              ];
            @endphp
            @foreach ($similarity['breakdown'] as $key => $val)
              @php $conf = $breakdownLabels[$key] ?? ['label' => $key, 'max' => 20, 'color' => 'bg-gray-400']; @endphp
              <div>
                <div class="flex justify-between text-xs text-gray-600 mb-1">
                  <span>{{ $conf['label'] }}</span>
                  <span>{{ $val }} / {{ $conf['max'] }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                  <div class="{{ $conf['color'] }} h-1.5 rounded-full" style="width: {{ $conf['max'] > 0 ? round(($val / $conf['max']) * 100) : 0 }}%"></div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
        @endif

        {{-- Processing Phases --}}
        @if (!empty($phases))
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-4" style="font-family: Vazirmatn, sans-serif;">مراحل پردازش</h3>
          <div class="space-y-2">
            @php
              $phaseLabels = [
                'extract'     => ['label' => 'استخراج ZIP', 'icon' => '📦'],
                'analyze'     => ['label' => 'آنالیز محتوا', 'icon' => '🔍'],
                'json_import' => ['label' => 'پردازش JSON', 'icon' => '⚙️'],
                'visual'      => ['label' => 'آنالیز بصری', 'icon' => '🖼️'],
                'similarity'  => ['label' => 'محاسبه شباهت', 'icon' => '📊'],
              ];
            @endphp
            @foreach ($phases as $key => $phase)
              @php $pl = $phaseLabels[$key] ?? ['label' => $key, 'icon' => '▶️']; @endphp
              <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                <div class="flex items-center gap-2 text-sm">
                  <span>{{ $pl['icon'] }}</span>
                  <span class="text-gray-700">{{ $pl['label'] }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-xs text-gray-400">{{ $phase['ms'] ?? 0 }}ms</span>
                  @if ($phase['status'] === 'done')
                    <span class="w-5 h-5 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs">✓</span>
                  @elseif ($phase['status'] === 'skipped')
                    <span class="w-5 h-5 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center text-xs">—</span>
                  @else
                    <span class="w-5 h-5 bg-red-100 text-red-500 rounded-full flex items-center justify-center text-xs">✗</span>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </div>
        @endif

        {{-- Quality Findings --}}
        @if (!empty($quality['findings']))
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-3" style="font-family: Vazirmatn, sans-serif;">یافته‌های کیفیت</h3>
          <ul class="space-y-2">
            @foreach ($quality['findings'] as $f)
              <li class="flex items-start gap-2 text-sm">
                @php $fc = $f['type'] === 'warning' ? 'text-amber-500' : 'text-blue-400'; @endphp
                <svg class="w-4 h-4 {{ $fc }} flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-gray-700">{{ $f['message'] }}</span>
              </li>
            @endforeach
          </ul>
        </div>
        @endif

        {{-- Similarity Findings --}}
        @if (!empty($similarity['findings']))
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-3" style="font-family: Vazirmatn, sans-serif;">یافته‌های شباهت</h3>
          <ul class="space-y-2">
            @foreach ($similarity['findings'] as $f)
              <li class="text-sm text-gray-700 flex items-start gap-2">
                <span class="text-indigo-400 mt-0.5">›</span>
                {{ $f }}
              </li>
            @endforeach
          </ul>
        </div>
        @endif

      </div>

      {{-- Right Column: Actions + Screenshots --}}
      <div class="space-y-5">

        {{-- Screenshots --}}
        @if (!empty($screenshots))
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-3" style="font-family: Vazirmatn, sans-serif;">اسکرین‌شات‌های قالب</h3>
          <div class="space-y-2">
            @foreach ($screenshots as $ss)
              <img src="{{ $ss }}" alt="screenshot" class="w-full rounded-lg border border-gray-200 object-cover" style="max-height:200px; object-position:top;">
            @endforeach
          </div>
        </div>
        @endif

        {{-- Install Action --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-4" style="font-family: Vazirmatn, sans-serif;">عملیات</h3>

          {{-- Install as Canvas Theme --}}
          <form action="{{ route('admin.theme-builder.zip-import.install', $pack->id) }}" method="POST" class="mb-3">
            @csrf
            <input type="hidden" name="name" value="{{ $pack->name }}">
            <input type="hidden" name="category" value="{{ $result['metadata']['category'] ?? 'corporate' }}">
            <button type="submit"
                    class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2"
                    style="font-family: Vazirmatn, sans-serif;">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
              </svg>
              نصب و باز کردن در Canvas
            </button>
          </form>

          {{-- Export JSON --}}
          <a href="{{ route('admin.theme-builder.zip-import.export', $pack->id) }}"
             class="w-full py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2 mb-3"
             style="font-family: Vazirmatn, sans-serif;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            دانلود JSON نرمال‌شده
          </a>

          {{-- Re-import --}}
          <a href="{{ route('admin.theme-builder.zip-import.form') }}"
             class="w-full py-2.5 px-4 border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2"
             style="font-family: Vazirmatn, sans-serif;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            وارد کردن ZIP جدید
          </a>
        </div>

        {{-- Pack Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
          <h3 class="text-sm font-bold text-gray-900 mb-3" style="font-family: Vazirmatn, sans-serif;">اطلاعات ThemePack</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">UUID</dt>
              <dd class="font-mono text-xs text-gray-700">{{ substr($pack->uuid ?? '', 0, 8) }}...</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">نسخه</dt>
              <dd class="text-gray-900">{{ $pack->version ?? '1.0.0' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">صفحات</dt>
              <dd class="text-gray-900">{{ count(($pack->pack_data ?? [])['pages'] ?? []) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">هدرها</dt>
              <dd class="text-gray-900">{{ count(($pack->pack_data ?? [])['headers'] ?? []) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">فوترها</dt>
              <dd class="text-gray-900">{{ count(($pack->pack_data ?? [])['footers'] ?? []) }}</dd>
            </div>
          </dl>
        </div>

        {{-- Marketplace Publish --}}
        @if (empty($result['theme_id']))
        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-5">
          <h3 class="text-sm font-bold text-indigo-900 mb-2" style="font-family: Vazirmatn, sans-serif;">انتشار در Marketplace</h3>
          <p class="text-xs text-indigo-700 mb-3">این قالب هنوز در Marketplace منتشر نشده. می‌توانید آن را به‌صورت Theme جداگانه منتشر کنید.</p>
          <form action="{{ route('admin.theme-builder.zip-import.install', $pack->id) }}" method="POST">
            @csrf
            <input type="hidden" name="name" value="{{ $pack->name }}">
            <input type="hidden" name="category" value="corporate">
            <button type="submit"
                    class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition-colors"
                    style="font-family: Vazirmatn, sans-serif;">
              نصب و ارسال به Canvas
            </button>
          </form>
        </div>
        @else
        <div class="bg-green-50 rounded-2xl border border-green-100 p-5">
          <div class="flex items-center gap-2 mb-2">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-sm font-bold text-green-900">در Marketplace منتشر شد</h3>
          </div>
          <a href="{{ route('admin.theme-marketplace.index') }}"
             class="text-xs text-green-700 hover:underline">مشاهده در Marketplace ›</a>
        </div>
        @endif

      </div>
    </div>
  </div>
</div>
@endsection
