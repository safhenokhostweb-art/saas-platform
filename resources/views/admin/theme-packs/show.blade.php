@extends('layouts.admin')

@section('title', $pack->name)

@section('content')
<div class="p-6 max-w-4xl mx-auto" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.theme-packs.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900">{{ $pack->name }}</h1>
            <span class="text-xs px-2 py-1 rounded-full {{ $pack->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $pack->is_active ? 'فعال' : 'غیرفعال' }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.theme-packs.export-json', $pack) }}"
                class="flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                JSON
            </a>
            <a href="{{ route('admin.theme-packs.export-zip', $pack) }}"
                class="flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                ZIP
            </a>
            <form method="POST" action="{{ route('admin.theme-packs.destroy', $pack) }}" onsubmit="return confirm('پک حذف شود؟')">
                @csrf @method('DELETE')
                <button type="submit" class="flex items-center gap-1.5 px-3 py-2 border border-red-200 rounded-lg text-sm text-red-600 hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    حذف
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-5">
        {{-- Metadata sidebar --}}
        <div class="col-span-1 space-y-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                <h3 class="font-semibold text-sm text-gray-700 border-b pb-2">اطلاعات پک</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">نسخه:</span><span class="font-mono text-xs">{{ $pack->version }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">مجوز:</span><span>{{ $pack->license_type }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">نویسنده:</span><span>{{ $pack->author ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">عمومی:</span><span>{{ $pack->is_public ? 'بله' : 'خیر' }}</span></div>
                </div>
            </div>

            {{-- Content summary --}}
            @php $summary = $pack->contentSummary(); @endphp
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">محتوای پک</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            صفحات
                        </div>
                        <span class="font-semibold">{{ $summary['pages'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/></svg>
                            قالب‌ها
                        </div>
                        <span class="font-semibold">{{ $summary['templates'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <span class="text-purple-400 text-xs font-bold">H</span> هدر
                        </div>
                        <span class="{{ ($summary['has_header'] ?? false) ? 'text-green-600 font-semibold' : 'text-gray-300' }}">
                            {{ ($summary['has_header'] ?? false) ? '✓' : '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <span class="text-pink-400 text-xs font-bold">F</span> فوتر
                        </div>
                        <span class="{{ ($summary['has_footer'] ?? false) ? 'text-green-600 font-semibold' : 'text-gray-300' }}">
                            {{ ($summary['has_footer'] ?? false) ? '✓' : '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                            استایل کلی
                        </div>
                        <span class="{{ ($summary['has_global_styles'] ?? false) ? 'text-green-600 font-semibold' : 'text-gray-300' }}">
                            {{ ($summary['has_global_styles'] ?? false) ? '✓' : '—' }}
                        </span>
                    </div>
                </div>
            </div>

            @if($pack->tags)
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-2">تگ‌ها</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($pack->tags as $tag)
                        <span class="text-xs px-2 py-1 bg-purple-50 text-purple-600 rounded-full">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-1">UUID</h3>
                <p class="text-xs font-mono text-gray-500 break-all">{{ $pack->uuid }}</p>
            </div>
        </div>

        {{-- Main content --}}
        <div class="col-span-2 space-y-4">
            @if($pack->description)
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-2">توضیحات</h3>
                <p class="text-sm text-gray-600">{{ $pack->description }}</p>
            </div>
            @endif

            @if($pack->preview_image_url)
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="p-3 border-b border-gray-100 text-xs font-medium text-gray-600">پیش‌نمایش</div>
                <img src="{{ $pack->preview_image_url }}" alt="Preview" class="w-full">
            </div>
            @endif

            {{-- Pack contents breakdown --}}
            @php $data = $pack->pack_data ?? []; @endphp
            @if(!empty($data['pages']))
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">صفحات ({{ count($data['pages']) }})</h3>
                <div class="space-y-1.5">
                    @foreach($data['pages'] as $key => $page)
                    <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg text-xs">
                        <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                        <span class="font-mono text-gray-500">{{ $key }}</span>
                        <span class="text-gray-700 font-medium">{{ $page['title'] ?? $page['name'] ?? '—' }}</span>
                        <span class="mr-auto text-gray-400">{{ count($page['blocks'] ?? []) }} بلوک</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($data['templates']))
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">قالب‌های ذخیره‌شده ({{ count($data['templates']) }})</h3>
                <div class="space-y-1.5">
                    @foreach($data['templates'] as $tpl)
                    <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg text-xs">
                        <span class="w-2 h-2 rounded-full bg-indigo-400 shrink-0"></span>
                        <span class="text-gray-700 font-medium">{{ $tpl['name'] ?? '—' }}</span>
                        <span class="text-gray-400">{{ $tpl['type'] ?? '' }}</span>
                        <span class="mr-auto text-gray-400">{{ $tpl['category'] ?? '' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Includes flags --}}
            @if($pack->includes)
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">شامل‌ها</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($pack->includes as $key => $val)
                        @if($val)
                        <span class="text-xs px-2.5 py-1 rounded-full bg-green-50 text-green-700 border border-green-200">{{ $key }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
