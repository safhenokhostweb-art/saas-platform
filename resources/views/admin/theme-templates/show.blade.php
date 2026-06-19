@extends('layouts.admin')

@section('title', $template->name)

@section('content')
<div class="p-6 max-w-4xl mx-auto" dir="rtl">

    {{-- Back + actions --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.theme-templates.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900">{{ $template->name }}</h1>
            <span class="text-xs px-2 py-1 rounded-full {{ $template->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $template->is_active ? 'فعال' : 'غیرفعال' }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.theme-templates.export', $template) }}"
                class="flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                دانلود JSON
            </a>
            <a href="{{ route('admin.theme-templates.edit', $template) }}"
                class="flex items-center gap-1.5 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-5">
        {{-- Left: metadata --}}
        <div class="col-span-1 space-y-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                <h3 class="font-semibold text-sm text-gray-700 border-b pb-2">اطلاعات قالب</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">نوع:</span><span class="font-medium text-indigo-600">{{ $template->typeLabel() }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">دسته:</span><span class="font-medium">{{ $template->categoryLabel() }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">نسخه:</span><span class="font-mono text-xs">{{ $template->version }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">مجوز:</span><span>{{ $template->license_type }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">استفاده:</span><span class="font-semibold">{{ $template->usage_count }}×</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">بلوک‌ها:</span><span class="font-semibold">{{ count($template->blocks ?? []) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">عمومی:</span><span>{{ $template->is_public ? 'بله' : 'خیر' }}</span></div>
                </div>
            </div>

            @if($template->tags)
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-2">تگ‌ها</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($template->tags as $tag)
                        <span class="text-xs px-2 py-1 bg-indigo-50 text-indigo-600 rounded-full">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            @if($template->author)
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-1">نویسنده</h3>
                <p class="text-sm text-gray-700">{{ $template->author }}</p>
            </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-1">UUID</h3>
                <p class="text-xs font-mono text-gray-500 break-all">{{ $template->uuid }}</p>
            </div>
        </div>

        {{-- Right: preview + blocks --}}
        <div class="col-span-2 space-y-4">
            @if($template->description)
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-2">توضیحات</h3>
                <p class="text-sm text-gray-600">{{ $template->description }}</p>
            </div>
            @endif

            @if($template->preview_image_url)
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="p-3 border-b border-gray-100 text-xs font-medium text-gray-600">پیش‌نمایش</div>
                <img src="{{ $template->preview_image_url }}" alt="Preview" class="w-full">
            </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">ساختار بلوک‌ها ({{ count($template->blocks ?? []) }} بلوک)</h3>
                <div class="space-y-1.5">
                    @foreach($template->blocks ?? [] as $block)
                    <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg text-xs">
                        <span class="w-2 h-2 rounded-full bg-indigo-400 shrink-0"></span>
                        <span class="font-mono text-gray-600">{{ $block['type'] ?? '?' }}</span>
                        @if(!empty($block['props']['label']))
                            <span class="text-gray-400">: {{ $block['props']['label'] }}</span>
                        @endif
                        @if(!empty($block['children']))
                            <span class="mr-auto text-gray-400">{{ count($block['children']) }} فرزند</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
