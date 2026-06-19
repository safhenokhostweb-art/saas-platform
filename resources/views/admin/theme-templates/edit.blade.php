@extends('layouts.admin')

@section('title', 'ویرایش: ' . $template->name)

@section('content')
<div class="p-6 max-w-3xl mx-auto" dir="rtl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.theme-templates.show', $template) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">ویرایش قالب</h1>
        <span class="text-sm text-gray-400 truncate">{{ $template->name }}</span>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-4">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.theme-templates.update', $template) }}" class="space-y-5">
        @csrf @method('PUT')

        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
            <h2 class="font-semibold text-gray-800 text-sm border-b pb-2">اطلاعات قالب</h2>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام قالب *</label>
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع *</label>
                    <select name="type" required class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        @foreach($options['types'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('type', $template->type) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی *</label>
                    <select name="category" required class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        @foreach($options['categories'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('category', $template->category) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="description" rows="3" class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none">{{ old('description', $template->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تگ‌ها (با کاما جدا کنید)</label>
                    <input type="text" name="tags" value="{{ old('tags', is_array($template->tags) ? implode(', ', $template->tags) : $template->tags) }}"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL تصویر بندانگشتی</label>
                    <input type="url" name="thumbnail_url" value="{{ old('thumbnail_url', $template->thumbnail_url) }}"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
            <div class="flex items-center justify-between border-b pb-2">
                <h2 class="font-semibold text-gray-800 text-sm">وضعیت و دسترسی</h2>
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $template->is_public))
                        class="rounded text-indigo-600">
                    <span class="text-sm text-gray-700">عمومی</span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active))
                        class="rounded text-indigo-600">
                    <span class="text-sm text-gray-700">فعال</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.theme-templates.show', $template) }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">انصراف</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>
@endsection
