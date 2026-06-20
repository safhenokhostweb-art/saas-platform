@extends('layouts.admin')

@section('title', 'پک‌های تم')

@section('content')
<div class="p-6 space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                پک‌های تم
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">مجموعه کامل قالب‌های قابل خروجی و واردسازی</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Import JSON --}}
            <label class="cursor-pointer flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                وارد JSON
                <form id="import-json-form" action="{{ route('admin.theme-packs.import-json') }}" method="POST" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input type="file" name="file" accept=".json" onchange="document.getElementById('import-json-form').submit()">
                </form>
            </label>
            {{-- Import ZIP --}}
            <label class="cursor-pointer flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                وارد ZIP
                <form id="import-zip-form" action="{{ route('admin.theme-packs.import-zip') }}" method="POST" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input type="file" name="file" accept=".zip" onchange="document.getElementById('import-zip-form').submit()">
                </form>
            </label>
            <a href="{{ route('admin.theme-packs.create') }}" class="flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                پک جدید
            </a>
        </div>
    </div>

    @if($packs->isEmpty())
        <div class="text-center py-16 bg-white border border-dashed border-gray-300 rounded-xl">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <p class="text-gray-500 text-sm">هیچ پکی ایجاد نشده.</p>
            <a href="{{ route('admin.theme-packs.create') }}" class="mt-3 inline-block text-purple-600 text-sm font-medium hover:underline">+ ایجاد اولین پک</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($packs as $pack)
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition flex flex-col">
                {{-- Preview banner --}}
                <div class="h-28 bg-gradient-to-br from-purple-50 to-indigo-100 flex items-center justify-center relative">
                    @if($pack->preview_image_url)
                        <img src="{{ $pack->preview_image_url }}" class="w-full h-full object-cover" alt="">
                    @else
                        <svg class="w-12 h-12 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    @endif
                    <span class="absolute top-2 right-2 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white/90 text-purple-700">
                        v{{ $pack->version }}
                    </span>
                </div>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="font-semibold text-sm text-gray-900 truncate">{{ $pack->name }}</h3>
                        <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full {{ $pack->is_public ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500' }}">
                            {{ $pack->is_public ? 'عمومی' : 'خصوصی' }}
                        </span>
                    </div>
                    @if($pack->description)
                        <p class="text-xs text-gray-500 mb-2 line-clamp-2">{{ $pack->description }}</p>
                    @endif

                    {{-- Content summary --}}
                    @php $summary = $pack->contentSummary(); @endphp
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        @if($summary['pages'] ?? 0)
                            <span class="text-[10px] px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded">{{ $summary['pages'] }} صفحه</span>
                        @endif
                        @if($summary['templates'] ?? 0)
                            <span class="text-[10px] px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded">{{ $summary['templates'] }} قالب</span>
                        @endif
                        @if($summary['has_header'] ?? false)
                            <span class="text-[10px] px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded">هدر</span>
                        @endif
                        @if($summary['has_footer'] ?? false)
                            <span class="text-[10px] px-1.5 py-0.5 bg-pink-50 text-pink-600 rounded">فوتر</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 mt-auto pt-3 border-t border-gray-100">
                        <a href="{{ route('admin.theme-packs.show', $pack) }}" class="flex-1 text-center text-xs py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 font-medium transition">
                            مشاهده
                        </a>
                        <a href="{{ route('admin.theme-packs.export-json', $pack) }}" class="text-xs py-1.5 px-3 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition" title="دانلود JSON">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                        <a href="{{ route('admin.theme-packs.export-zip', $pack) }}" class="text-xs py-1.5 px-3 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition" title="دانلود ZIP">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        </a>
                        <form method="POST" action="{{ route('admin.theme-packs.destroy', $pack) }}" onsubmit="return confirm('پک حذف شود؟')">
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
        <div class="mt-4">{{ $packs->links() }}</div>
    @endif
</div>
@endsection
