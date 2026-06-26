@extends('layouts.admin')

@section('title', 'ویرایش باندل: ' . $themeBundle->name)

@section('content')
<div class="p-6 max-w-3xl mx-auto space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.theme-bundles.index') }}"
           class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویرایش باندل</h1>
            <p class="text-sm text-gray-500 font-mono">{{ $themeBundle->slug }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <form action="{{ route('admin.theme-bundles.update', $themeBundle) }}" method="POST" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <h2 class="font-semibold text-gray-800 border-b border-gray-100 pb-3">اطلاعات اصلی</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام باندل <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $themeBundle->name) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                    @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسلاگ (غیرقابل تغییر)</label>
                    <input type="text" value="{{ $themeBundle->slug }}" disabled dir="ltr"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono bg-gray-50 text-gray-400 cursor-not-allowed">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">{{ old('description', $themeBundle->description) }}</textarea>
                @error('description')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">قیمت (ریال) <span class="text-rose-500">*</span></label>
                    <input type="number" name="price" value="{{ old('price', $themeBundle->price) }}" min="0" required dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                    @error('price')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس تصویر شاخص</label>
                    <input type="url" name="thumbnail" value="{{ old('thumbnail', $themeBundle->thumbnail) }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                           placeholder="https://...">
                    @error('thumbnail')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $themeBundle->is_active) ? 'checked' : '' }}
                           class="w-4 h-4 text-violet-600 rounded">
                    <span class="text-sm text-gray-700">فعال</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1"
                           {{ old('is_featured', $themeBundle->is_featured) ? 'checked' : '' }}
                           class="w-4 h-4 text-amber-500 rounded">
                    <span class="text-sm text-gray-700">ویژه (نمایش اول)</span>
                </label>
            </div>
        </div>

        {{-- Theme picker --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4"
             x-data="bundlePicker({{ json_encode($themeBundle->theme_ids ?? []) }})">
            <h2 class="font-semibold text-gray-800 border-b border-gray-100 pb-3">
                انتخاب قالب‌ها
                <span class="text-sm text-gray-400 font-normal">(حداقل ۲ قالب)</span>
            </h2>

            @error('theme_ids')<div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg px-3 py-2 text-sm">{{ $message }}</div>@enderror

            <input type="text" placeholder="جستجو در قالب‌ها..." x-model="search"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-80 overflow-y-auto pr-1">
                @foreach($themes as $theme)
                    <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-violet-50 hover:border-violet-300 transition"
                           x-show="!search || '{{ strtolower($theme->name) }}'.includes(search.toLowerCase())">
                        <input type="checkbox" name="theme_ids[]" value="{{ $theme->id }}"
                               :checked="selected.includes({{ $theme->id }})"
                               x-on:change="toggle({{ $theme->id }})"
                               class="w-4 h-4 text-violet-600 rounded shrink-0">
                        @if($theme->thumbnail)
                            <img src="{{ $theme->thumbnail }}" alt="{{ $theme->name }}"
                                 class="w-10 h-10 rounded-lg object-cover shrink-0">
                        @else
                            <div class="w-10 h-10 bg-violet-100 rounded-lg flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 text-sm truncate">{{ $theme->name }}</p>
                            <p class="text-xs text-gray-400">@numFa($theme->marketplace_price ?? 0) ریال</p>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="text-xs text-gray-400 pt-1">
                <span x-text="selected.length"></span> قالب انتخاب شده
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.theme-bundles.index') }}"
               class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium">
                انصراف
            </a>
            <button type="submit"
                    class="px-6 py-2.5 bg-violet-600 text-white rounded-xl hover:bg-violet-700 text-sm font-bold">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function bundlePicker(initial) {
    return {
        search: '',
        selected: initial || [],
        toggle(id) {
            const idx = this.selected.indexOf(id);
            if (idx >= 0) this.selected.splice(idx, 1);
            else this.selected.push(id);
        }
    };
}
</script>
@endpush
@endsection
