@extends('layouts.admin')

@section('title', 'قالب جدید')

@section('content')
<div class="p-6 max-w-3xl mx-auto" dir="rtl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.theme-templates.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">قالب جدید</h1>
    </div>

    <form method="POST" action="{{ route('admin.theme-templates.store') }}" class="space-y-5">
        @csrf

        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
            <h2 class="font-semibold text-gray-800 text-sm border-b pb-2">اطلاعات اصلی</h2>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام قالب *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع *</label>
                    <select name="type" required class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        @foreach($options['types'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('type') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی *</label>
                    <select name="category" required class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        @foreach($options['categories'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('category') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="description" rows="3" class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تگ‌ها (با کاما جدا کنید)</label>
                    <input type="text" name="tags" value="{{ old('tags') }}" placeholder="hero, dark, minimal"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نویسنده</label>
                    <input type="text" name="author" value="{{ old('author') }}"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نسخه</label>
                    <input type="text" name="version" value="{{ old('version', '1.0.0') }}"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع مجوز</label>
                    <select name="license_type" class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <option value="free">رایگان</option>
                        <option value="premium">پریمیوم</option>
                        <option value="commercial">تجاری</option>
                        <option value="mit">MIT</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
            <h2 class="font-semibold text-gray-800 text-sm border-b pb-2">داده بلوک‌ها (JSON) *</h2>
            <p class="text-xs text-gray-500">آرایه JSON بلوک‌ها را وارد کنید. این داده از کنواس بیلدر تم کپی می‌شود.</p>
            <textarea name="blocks" rows="10" required
                placeholder='[{"id":"blk_abc","type":"hero","props":{...}}]'
                class="w-full text-xs font-mono px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">{{ old('blocks') }}</textarea>
            @error('blocks')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))
                    class="rounded text-indigo-600">
                <span class="text-sm text-gray-700">عمومی (قابل مشاهده در مارکتپلیس آینده)</span>
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.theme-templates.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">انصراف</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                ذخیره قالب
            </button>
        </div>
    </form>
</div>
@endsection
