@extends('layouts.admin')

@section('title', 'کتابخانه قالب‌ها')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                کتابخانه قالب‌ها
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">قالب‌های آماده برای بیلدر تم</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Import --}}
            <label class="cursor-pointer flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                وارد کردن JSON
                <form id="import-form" action="{{ route('admin.theme-templates.import') }}" method="POST" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input type="file" name="file" accept=".json" onchange="document.getElementById('import-form').submit()">
                </form>
            </label>
            <a href="{{ route('admin.theme-templates.create') }}" class="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                قالب جدید
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 bg-white border border-gray-200 rounded-xl p-4">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="جستجو در قالب‌ها…"
            class="flex-1 min-w-48 text-sm px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">

        <select name="type" class="text-sm px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <option value="">همه انواع</option>
            @foreach($options['types'] as $val => $label)
                <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="category" class="text-sm px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <option value="">همه دسته‌ها</option>
            @foreach($options['categories'] as $val => $label)
                <option value="{{ $val }}" @selected(request('category') === $val)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            فیلتر
        </button>
        @if(request()->hasAny(['search','type','category']))
            <a href="{{ route('admin.theme-templates.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">پاک کردن</a>
        @endif
    </form>

    {{-- Grid --}}
    @if($templates->isEmpty())
        <div class="text-center py-16 bg-white border border-dashed border-gray-300 rounded-xl">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
            <p class="text-gray-500 text-sm">هیچ قالبی یافت نشد.</p>
            <a href="{{ route('admin.theme-templates.create') }}" class="mt-3 inline-block text-indigo-600 text-sm font-medium hover:underline">+ اضافه کردن اولین قالب</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($templates as $template)
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition group flex flex-col">
                {{-- Thumbnail --}}
                <div class="aspect-video bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center relative overflow-hidden">
                    @if($template->thumbnail_url)
                        <img src="{{ $template->thumbnail_url }}" alt="{{ $template->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="text-center p-4">
                            <div class="w-10 h-10 mx-auto mb-2 bg-indigo-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/></svg>
                            </div>
                            <span class="text-xs text-slate-400">{{ $template->typeLabel() }}</span>
                        </div>
                    @endif
                    {{-- Type badge --}}
                    <span class="absolute top-2 right-2 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white/90 text-indigo-700 shadow-sm">
                        {{ $template->typeLabel() }}
                    </span>
                    {{-- Usage count --}}
                    @if($template->usage_count > 0)
                    <span class="absolute top-2 left-2 text-[10px] px-1.5 py-0.5 rounded-full bg-black/30 text-white">
                        {{ $template->usage_count }}× استفاده
                    </span>
                    @endif
                </div>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="font-semibold text-sm text-gray-900 truncate">{{ $template->name }}</h3>
                        <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $template->categoryLabel() }}</span>
                    </div>
                    @if($template->description)
                        <p class="text-xs text-gray-500 mb-2 line-clamp-2">{{ $template->description }}</p>
                    @endif
                    @if($template->tags)
                        <div class="flex flex-wrap gap-1 mb-3">
                            @foreach(array_slice($template->tags, 0, 3) as $tag)
                                <span class="text-[10px] px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex items-center gap-2 mt-auto pt-3 border-t border-gray-100">
                        <a href="{{ route('admin.theme-templates.show', $template) }}" class="flex-1 text-center text-xs py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-medium transition">
                            مشاهده
                        </a>
                        <a href="{{ route('admin.theme-templates.export', $template) }}" class="text-xs py-1.5 px-3 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition" title="دانلود JSON">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                        <form method="POST" action="{{ route('admin.theme-templates.destroy', $template) }}" onsubmit="return confirm('حذف شود؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs py-1.5 px-3 rounded-lg border border-red-100 text-red-500 hover:bg-red-50 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $templates->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
