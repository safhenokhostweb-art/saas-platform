@extends(config('forms.layouts.admin'))

@section('header', 'ایجاد فرم جدید')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <form method="POST" action="{{ route('admin.forms.store') }}" class="space-y-6">
            @csrf

            <div>
                <label class="block mb-1 font-medium text-gray-700">عنوان فرم</label>
                <input type="text" name="title" value="{{ old('title') }}"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       placeholder="عنوان فرم را وارد کنید">
                @error('title')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">نامک (اسلاگ)</label>
                <input type="text" name="slug" value="{{ old('slug') }}"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       placeholder="در صورت خالی بودن، به صورت خودکار ساخته می‌شود">
                @error('slug')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if(config('forms.seo_enabled', true))
            <div>
                <label class="block mb-1 font-medium text-gray-700">عنوان متا (Meta Title)</label>
                <input type="text" name="meta_title" value="{{ old('meta_title') }}"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                @error('meta_title')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">توضیحات متا (Meta Description)</label>
                <textarea name="meta_description" rows="2"
                          class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">{{ old('meta_description') }}</textarea>
                @error('meta_description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">آدرس کنونیکال (Canonical URL)</label>
                <input type="text" name="canonical_url" value="{{ old('canonical_url') }}"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                @error('canonical_url')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">ربات متا (Meta Robots)</label>
                <input type="text" name="meta_robots" value="{{ old('meta_robots') }}"
                       class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       placeholder="index, follow">
                @error('meta_robots')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">متن مقدمه</label>
                <textarea name="intro_text" rows="5"
                          class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">{{ old('intro_text') }}</textarea>
                @error('intro_text')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            @endif

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" checked id="is_active"
                       class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="font-medium text-gray-700">فرم فعال باشد</label>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t">
                <button type="submit"
                        class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl hover:opacity-90 transition inline-flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    ذخیره فرم
                </button>
                <a href="{{ route('admin.forms.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-3">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
