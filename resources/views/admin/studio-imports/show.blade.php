@extends('admin.layout')

@section('title', 'پیش‌نمایش: ' . $pack->name)

@section('content')
@php $summary = $pack->contentSummary(); $data = $pack->pack_data ?? []; @endphp

<div class="space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="{{ route('admin.studio-imports.index') }}"
         class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $pack->name }}</h1>
        <p class="text-gray-500 text-sm">پک وارد شده از TB-AI Studio</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      @unless($pack->is_public)
      <form action="{{ route('admin.studio-imports.publish', $pack) }}" method="POST">
        @csrf
        <button type="submit"
                class="px-5 py-2.5 bg-green-600 text-white rounded-xl font-semibold text-sm hover:bg-green-700 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          انتشار در مارکت‌پلیس
        </button>
      </form>
      @endunless
      <a href="{{ route('admin.theme-packs.show', $pack) }}"
         class="px-4 py-2.5 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-medium hover:bg-indigo-100">
        ویرایش پک
      </a>
    </div>
  </div>

  {{-- Status badges --}}
  <div class="flex items-center gap-3">
    @if($pack->is_public)
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> منتشرشده
      </span>
    @else
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> در انتظار انتشار
      </span>
    @endif
    @foreach(array_slice($pack->tags ?? [], 0, 5) as $tag)
      <span class="px-2.5 py-1 rounded-full text-xs bg-indigo-100 text-indigo-700">{{ $tag }}</span>
    @endforeach
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Info + Stats --}}
    <div class="lg:col-span-1 space-y-4">

      {{-- Pack Info --}}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">اطلاعات پک</h3>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between">
            <dt class="text-gray-500">UUID</dt>
            <dd class="text-gray-800 font-mono text-xs">{{ substr($pack->uuid, 0, 16) }}...</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">نسخه</dt>
            <dd class="font-medium text-gray-800">{{ $pack->version }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">نویسنده</dt>
            <dd class="font-medium text-gray-800">{{ $pack->author ?: '—' }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">مجوز</dt>
            <dd class="font-medium text-gray-800">{{ $pack->license_type }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">تاریخ ایجاد</dt>
            <dd class="text-gray-800">{{ $pack->created_at->diffForHumans() }}</dd>
          </div>
        </dl>
      </div>

      {{-- Content Summary --}}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">محتوا</h3>
        <div class="grid grid-cols-2 gap-3">
          @php
            $stats = [
              'صفحات'    => $summary['pages'],
              'قالب‌ها'  => $summary['templates'],
              'هدرها'    => $summary['headers'],
              'فوترها'   => $summary['footers'],
            ];
          @endphp
          @foreach($stats as $label => $val)
          <div class="text-center p-3 bg-gray-50 rounded-xl">
            <p class="text-2xl font-bold text-indigo-600">{{ $val }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $label }}</p>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Global Styles --}}
      @if(!empty($data['global_styles']))
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">رنگ‌بندی قالب</h3>
        <div class="space-y-2">
          @foreach(['primary_color','secondary_color','accent_color','background_color','text_color'] as $colorKey)
            @if(!empty($data['global_styles'][$colorKey]))
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg border border-gray-200 flex-shrink-0"
                   style="background: {{ $data['global_styles'][$colorKey] }}"></div>
              <div>
                <p class="text-xs text-gray-500">{{ $colorKey }}</p>
                <p class="text-sm font-mono text-gray-800">{{ $data['global_styles'][$colorKey] }}</p>
              </div>
            </div>
            @endif
          @endforeach
        </div>
      </div>
      @endif

    </div>

    {{-- Right: Pages + Templates --}}
    <div class="lg:col-span-2 space-y-4">

      {{-- Description --}}
      @if($pack->description)
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-2">توضیحات</h3>
        <p class="text-gray-600 text-sm leading-relaxed">{{ $pack->description }}</p>
      </div>
      @endif

      {{-- Pages --}}
      @if(!empty($data['pages']))
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">صفحات ({{ count($data['pages']) }})</h3>
        </div>
        <div class="divide-y divide-gray-50">
          @foreach($data['pages'] as $page)
          <div class="px-5 py-3 flex items-center justify-between">
            <div>
              <p class="font-medium text-gray-900 text-sm">{{ $page['title'] ?? 'بدون عنوان' }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ $page['slug'] ?: '/' }}</p>
            </div>
            <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full">
              {{ count($page['blocks'] ?? []) }} بلوک
            </span>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Templates --}}
      @if(!empty($data['templates']))
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">قالب‌های بخش ({{ count($data['templates']) }})</h3>
        </div>
        <div class="divide-y divide-gray-50">
          @foreach($data['templates'] as $tpl)
          <div class="px-5 py-3 flex items-center justify-between">
            <div>
              <p class="font-medium text-gray-900 text-sm">{{ $tpl['name'] ?? 'بدون نام' }}</p>
              <p class="text-xs text-gray-400">{{ $tpl['category'] ?? '' }} / {{ $tpl['type'] ?? 'section' }}</p>
            </div>
            <span class="text-xs bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full">
              {{ count($tpl['blocks'] ?? []) }} بلوک
            </span>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Export actions --}}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">عملیات</h3>
        <div class="flex flex-wrap gap-3">
          <a href="{{ route('admin.theme-packs.export-zip', $pack) }}"
             class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            دانلود ZIP
          </a>
          <a href="{{ route('admin.theme-packs.export-json', $pack) }}"
             class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 flex items-center gap-2">
            دانلود JSON
          </a>
          <form action="{{ route('admin.studio-imports.destroy', $pack) }}" method="POST"
                onsubmit="return confirm('حذف شود؟')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-700 rounded-lg text-sm font-medium hover:bg-red-100">
              حذف
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>

</div>
@endsection
