@extends('layouts.admin')
@section('title', 'نتیجه وارد کردن JSON قالب')

@section('content')
@php
    $result  = $importResult ?? [];
    $quality = $result['quality'] ?? $pack->pack_data['import_meta']['stats'] ?? [];
    $stats   = $result['stats']   ?? $pack->pack_data['import_meta']['stats'] ?? [];
    $score   = $quality['score']  ?? 0;
    $grade   = $quality['grade']  ?? 'N/A';
    $gradeColor = match(true) {
        $score >= 80 => 'text-emerald-600',
        $score >= 60 => 'text-amber-600',
        default      => 'text-red-500',
    };
    $gradeBg = match(true) {
        $score >= 80 => 'bg-emerald-50 border-emerald-200',
        $score >= 60 => 'bg-amber-50 border-amber-200',
        default      => 'bg-red-50 border-red-200',
    };
    $sourceLabel = $result['source_label'] ?? ($pack->pack_data['import_meta']['source_label'] ?? 'نامشخص');
@endphp

<div dir="rtl" class="max-w-4xl mx-auto space-y-6">

{{-- ─── Header ──────────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
             style="background:linear-gradient(135deg,#10b981,#06b6d4)">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold text-gray-900">واردسازی کامل شد</h1>
            <p class="text-sm text-gray-500">{{ $pack->name }} — از {{ $sourceLabel }}</p>
        </div>
    </div>
    <a href="{{ route('admin.theme-builder.json-import.form') }}"
       class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        وارد کردن جدید
    </a>
</div>

{{-- ─── Quality card ────────────────────────────────────────────────────── --}}
<div class="rounded-2xl border p-6 {{ $gradeBg }}">
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">کیفیت واردسازی</div>
            <div class="text-4xl font-black {{ $gradeColor }}">{{ $score }}<span class="text-lg">/100</span></div>
        </div>
        <div class="text-center">
            <div class="text-3xl font-black {{ $gradeColor }}">{{ $grade }}</div>
            <div class="text-xs text-gray-500 mt-1">
                {{ isset($result['duration_ms']) ? $result['duration_ms'] . ' ms' : '' }}
            </div>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
        <div class="h-2 rounded-full transition-all duration-1000"
             style="width:{{ $score }}%;background:{{ $score >= 80 ? '#10b981' : ($score >= 60 ? '#f59e0b' : '#ef4444') }}">
        </div>
    </div>

    {{-- Findings --}}
    @if(!empty($quality['findings']))
    <div class="space-y-1">
        @foreach($quality['findings'] as $finding)
        <div class="flex items-start gap-2 text-xs">
            <span class="{{ $finding['type'] === 'warning' ? 'text-amber-500' : 'text-blue-500' }} mt-0.5">●</span>
            <span class="text-gray-600">{{ $finding['message'] }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ─── Stats grid ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-4">
    @foreach([
        ['صفحات', $stats['page_count'] ?? $packSummary['pages'], 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', '#6366f1'],
        ['کل بلوک‌ها', $stats['total_blocks'] ?? '—', 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', '#8b5cf6'],
        ['انواع بلوک', $stats['block_type_variety'] ?? '—', 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', '#06b6d4'],
        ['منبع', $sourceLabel, 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064', '#10b981'],
    ] as [$label, $value, $icon, $color])
    <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-2" style="background:{{ $color }}18">
            <svg class="w-4 h-4" style="color:{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
            </svg>
        </div>
        <div class="text-lg font-black text-gray-900">{{ $value }}</div>
        <div class="text-xs text-gray-500">{{ $label }}</div>
    </div>
    @endforeach
</div>

{{-- ─── Pack summary ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-bold text-gray-700 mb-3">محتوای ThemePack</h3>
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div class="flex items-center gap-2 p-3 rounded-xl {{ $packSummary['global_styles'] ? 'bg-emerald-50' : 'bg-gray-50' }}">
            <span class="{{ $packSummary['global_styles'] ? 'text-emerald-600' : 'text-gray-400' }}">
                {{ $packSummary['global_styles'] ? '✓' : '—' }}
            </span>
            <span class="{{ $packSummary['global_styles'] ? 'text-emerald-700' : 'text-gray-500' }} font-medium">استایل‌های کلی</span>
        </div>
        <div class="flex items-center gap-2 p-3 rounded-xl {{ $packSummary['headers'] > 0 ? 'bg-emerald-50' : 'bg-gray-50' }}">
            <span class="{{ $packSummary['headers'] > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                {{ $packSummary['headers'] > 0 ? '✓' : '—' }}
            </span>
            <span class="{{ $packSummary['headers'] > 0 ? 'text-emerald-700' : 'text-gray-500' }} font-medium">
                هدر ({{ $packSummary['headers'] }})
            </span>
        </div>
        <div class="flex items-center gap-2 p-3 rounded-xl {{ $packSummary['footers'] > 0 ? 'bg-emerald-50' : 'bg-gray-50' }}">
            <span class="{{ $packSummary['footers'] > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                {{ $packSummary['footers'] > 0 ? '✓' : '—' }}
            </span>
            <span class="{{ $packSummary['footers'] > 0 ? 'text-emerald-700' : 'text-gray-500' }} font-medium">
                فوتر ({{ $packSummary['footers'] }})
            </span>
        </div>
        <div class="flex items-center gap-2 p-3 rounded-xl {{ $packSummary['pages'] > 0 ? 'bg-indigo-50' : 'bg-gray-50' }}">
            <span class="{{ $packSummary['pages'] > 0 ? 'text-indigo-600' : 'text-gray-400' }}">
                {{ $packSummary['pages'] }}
            </span>
            <span class="text-indigo-700 font-medium">صفحه</span>
        </div>
        <div class="col-span-2 flex items-center gap-2 p-3 rounded-xl bg-gray-50 text-xs text-gray-500">
            UUID: <span class="font-mono">{{ $pack->uuid }}</span>
        </div>
    </div>
</div>

{{-- ─── Block types breakdown --}}
@if(!empty($stats['block_types']))
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-bold text-gray-700 mb-3">انواع بلوک‌های وارد شده</h3>
    <div class="flex flex-wrap gap-2">
        @foreach($stats['block_types'] as $type => $count)
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
            <span>{{ $type }}</span>
            <span class="bg-indigo-200 text-indigo-800 rounded-full w-4 h-4 flex items-center justify-center text-xs font-bold">{{ $count }}</span>
        </span>
        @endforeach
    </div>
</div>
@endif

{{-- ─── Action panel ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
    <h3 class="text-sm font-bold text-gray-700">اقدامات</h3>

    {{-- Install as Theme --}}
    <form action="{{ route('admin.theme-builder.json-import.install', $pack->id) }}" method="POST"
          class="flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-semibold text-gray-600 mb-1">نام قالب برای نصب</label>
            <input type="text" name="name" value="{{ $pack->name }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="w-48">
            <label class="block text-xs font-semibold text-gray-600 mb-1">دسته‌بندی</label>
            <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach($allCategories as $key => $label)
                <option value="{{ $key }}" {{ $key === 'corporate' ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold text-white flex-shrink-0"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 2px 8px rgba(99,102,241,.35)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            نصب و باز کردن در Canvas
        </button>
    </form>

    <hr class="border-gray-100">

    {{-- Secondary actions --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.theme-builder.json-import.export', $pack->id) }}"
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50 transition-all">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            دانلود JSON نرمال‌شده
        </a>
        <a href="{{ route('admin.theme-builder.json-import.form') }}"
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50 transition-all">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            وارد کردن قالب دیگر
        </a>
        <a href="{{ route('admin.theme-builder.index') }}"
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50 transition-all">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            همه قالب‌ها
        </a>
    </div>
</div>

</div>
@endsection
